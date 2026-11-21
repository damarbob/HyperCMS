<?php

namespace App\Data\Entries;

use Closure;
use Config\HyperEngine;

/**
 * Builds a saved list: the ids of the entries that one find filter, one search and one sort matched, in order.
 *
 * The candidates come from SQL, so they hold no fields. Only the entries that need their fields are read, a chunk at
 * a time through `EntryPayloads`, and a chunk is dropped before the next is read, so the list keeps ids and sort keys
 * and never payloads. When the sort is a fixed column the candidates already arrive in that order and keep it.
 */
final class ListScan
{
    public function __construct(private ?EntryPayloads $payloads = null, private ?HyperEngine $config = null)
    {
    }

    /**
     * @param list<array{id: int|string, model_id: int|string, sd_entry_id: int|string|null, payload_bytes: int|string}> $candidates
     *                 In the order they should keep when `sort` is empty.
     * @param array{
     *     find?: array{field: string, value: string}|null,
     *     search?: string,
     *     sort?: array{kind: string, field: string|null, dir: string}|null,
     * } $spec `search` is already HTML escaped. `sort.kind` is `text`, `number`, `date` or `fields`, the last
     *         meaning the text of all the fields. `dir` is `ASC` or `DESC`.
     * @param Closure(list<array>): array<int, array{fields: array, text?: string|null}> $read Reads one chunk of candidates by public id.
     * @param array<int, true> $matched Ids that already match the search without their fields, such as by model name or username.
     *
     * @return array{rows: list<array{0: int, 1: int}>, total: int} Each row is the entry id and its model id. `total`
     *                                                              counts the entries that passed the find filter, before the search.
     *
     * @throws ScanLimitExceeded When more entries need their fields than the scan limit allows.
     */
    public function run(array $candidates, array $spec, Closure $read, array $matched = []): array
    {
        $find   = $spec['find'] ?? null;
        $search = $spec['search'] ?? '';
        $sort   = $spec['sort'] ?? null;

        $needs = [];
        foreach ($candidates as $candidate) {
            if ($find !== null || $sort !== null || ($search !== '' && ! isset($matched[(int) $candidate['id']]))) {
                $needs[] = $candidate;
            }
        }

        $limit = ($this->config ??= config('HyperEngine'))->scanLimit;
        if ($limit > 0 && count($needs) > $limit) {
            log_message('notice', 'Scan limit of {limit} exceeded, {entries} entries would be read.', ['limit' => $limit, 'entries' => count($needs)]);

            throw new ScanLimitExceeded(count($needs), $limit);
        }

        $byteLimit = $this->config->scanBytes;
        $bytes     = array_sum(array_map(static fn (array $candidate): int => (int) ($candidate['payload_bytes'] ?? 0), $needs));
        if ($byteLimit > 0 && $bytes > $byteLimit) {
            log_message('notice', 'Scan byte limit of {limit} exceeded, {bytes} bytes would be read.', ['limit' => $byteLimit, 'bytes' => $bytes]);

            throw new ScanLimitExceeded(count($needs), $byteLimit, 'bytes');
        }

        // Read the fields once, a chunk at a time, and keep only what the verdict needs.
        $verdicts = [];
        foreach (($this->payloads ??= new EntryPayloads())->chunks($needs) as $chunk) {
            foreach ($read($chunk) as $id => $entry) {
                $verdicts[$id] = $this->judge($entry, $find, $search, $sort, isset($matched[$id]));
            }
        }

        $needIds = array_flip(array_map(static fn (array $candidate): int => (int) $candidate['id'], $needs));

        $total = 0;
        $kept  = [];
        foreach ($candidates as $candidate) {
            $id = (int) $candidate['id'];

            if (isset($needIds[$id])) {
                // The fields could not be read, so the row is skipped and not counted.
                if (! isset($verdicts[$id])) {
                    continue;
                }
                $verdict = $verdicts[$id];
            } else {
                $verdict = ['found' => true, 'match' => true, 'key' => null];
            }

            if (! $verdict['found']) {
                continue;
            }
            $total++;
            if ($verdict['match']) {
                $kept[] = [$id, (int) $candidate['model_id'], $verdict['key']];
            }
        }

        if ($sort !== null) {
            $descending = $sort['dir'] === 'DESC';
            usort($kept, static function (array $a, array $b) use ($descending): int {
                $cmp = FieldRules::compare($a[2], $b[2]);

                return ($descending ? -$cmp : $cmp) ?: $a[0] <=> $b[0];
            });
        }

        return ['rows' => array_map(static fn (array $row): array => [$row[0], $row[1]], $kept), 'total' => $total];
    }

    /**
     * @return array{found: bool, match: bool, key: string|float|null}
     */
    private function judge(array $entry, ?array $find, string $search, ?array $sort, bool $matched): array
    {
        $fields = $entry['fields'];

        if ($find !== null && ! FieldRules::findMatches($fields, $find['field'], $find['value'])) {
            return ['found' => false, 'match' => false, 'key' => null];
        }

        $text = $entry['text'] ?? null;
        $match = true;
        if ($search !== '' && ! $matched) {
            $text ??= FieldRules::fieldsText($fields);
            $match = FieldRules::contains($text, $search);
        }

        $key = null;
        if ($match && $sort !== null) {
            if ($sort['kind'] === 'fields') {
                $key = FieldRules::sortFold($text ?? FieldRules::fieldsText($fields));
            } else {
                $key = FieldRules::sortKey($sort['kind'], $fields, (string) $sort['field']);
            }
        }

        return ['found' => true, 'match' => $match, 'key' => $key];
    }
}
