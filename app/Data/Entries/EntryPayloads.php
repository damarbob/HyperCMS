<?php

namespace App\Data\Entries;

use Closure;
use Config\HyperEngine;
use Generator;
use StarDust\StarDust;

/**
 * Reads the current fields of live entries from StarDust, a bounded chunk at a time.
 *
 * `getMany()` takes at most 1,000 ids and has no byte cap of its own, and the stress data has bodies of 1 to 3 MB
 * while PHP in Docker stops at 128 MB. A chunk therefore holds at most `HyperEngine::chunkEntries` entries and
 * about `HyperEngine::chunkBytes` of stored fields, sized from `hyper_entries.payload_bytes`. One entry larger
 * than the cap is read alone.
 */
class EntryPayloads
{
    /**
     * @param Closure(list<int>): array<int, object{fields: array}>|null $reader Reads StarDust entries by StarDust id, for a test to replace.
     */
    public function __construct(
        private ?StarDust $engine = null,
        private ?Closure $reader = null,
        private ?HyperEngine $config = null,
    ) {
    }

    /**
     * Cut the rows into chunks that stay within the entry and byte caps, keeping their order.
     *
     * @param list<array{id: int|string, sd_entry_id: int|string|null, payload_bytes: int|string}> $rows
     *
     * @return Generator<int, list<array{id: int|string, sd_entry_id: int|string|null, payload_bytes: int|string}>>
     */
    public function chunks(array $rows): Generator
    {
        $config   = $this->config();
        $maxCount = max(1, min(1000, $config->chunkEntries));
        $maxBytes = max(1, $config->chunkBytes);

        $chunk = [];
        $bytes = 0;
        foreach ($rows as $row) {
            $size = (int) ($row['payload_bytes'] ?? 0);
            if ($chunk !== [] && (count($chunk) >= $maxCount || $bytes + $size > $maxBytes)) {
                yield $chunk;
                $chunk = [];
                $bytes = 0;
            }
            $chunk[] = $row;
            $bytes  += $size;
        }

        if ($chunk !== []) {
            yield $chunk;
        }
    }

    /**
     * Read the fields of one chunk. An entry StarDust does not return is logged and left out, so the caller skips its row.
     *
     * @param list<array{id: int|string, sd_entry_id: int|string|null, payload_bytes: int|string}> $chunk
     *
     * @return array<int, array> The fields, by the public id of each entry.
     */
    public function read(array $chunk): array
    {
        $ids = [];
        foreach ($chunk as $row) {
            if ($row['sd_entry_id'] !== null) {
                $ids[] = (int) $row['sd_entry_id'];
            }
        }
        if ($ids === []) {
            return [];
        }

        $entries = $this->reader()($ids);

        $fields = [];
        foreach ($chunk as $row) {
            $entry = $row['sd_entry_id'] === null ? null : ($entries[(int) $row['sd_entry_id']] ?? null);
            if ($entry === null) {
                log_message('warning', 'Entry {id} points at StarDust entry {sd}, which StarDust did not return. The row is skipped.', ['id' => $row['id'], 'sd' => $row['sd_entry_id'] ?? 'none']);

                continue;
            }
            $fields[(int) $row['id']] = $entry->fields;
        }

        return $fields;
    }

    /**
     * Read the fields of any number of entries, chunk by chunk.
     *
     * @param list<array{id: int|string, sd_entry_id: int|string|null, payload_bytes: int|string}> $rows
     *
     * @return array<int, array>
     */
    public function fetch(array $rows): array
    {
        $fields = [];
        foreach ($this->chunks($rows) as $chunk) {
            $fields += $this->read($chunk);
        }

        return $fields;
    }

    private function reader(): Closure
    {
        return $this->reader ??= function (array $ids): array {
            $this->engine ??= service('stardust');

            return $this->engine->getMany((int) $this->config()->tenantId, $ids);
        };
    }

    private function config(): HyperEngine
    {
        return $this->config ??= config('HyperEngine');
    }
}
