<?php

namespace App\Libraries\Upgrade;

use CodeIgniter\Database\BaseConnection;
use StarDust\StarDust;

/**
 * Checks the result of the upgrade against the old tables, which it never changes.
 *
 * Two things must hold. For every model, the live entries in `hyper_entries` must all come back from
 * StarDust. And the fields StarDust returns for each live entry must equal that entry's current version
 * in `entry_data`, converted the way the upgrade converts it. The comparison is on the decoded values,
 * because MySQL rewrites the text of a JSON column, so key order and spacing differ and mean nothing.
 */
final class Verifier
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly StarDust $engine,
        private readonly int $tenantId,
    ) {
    }

    /**
     * @return array{
     *     models: list<array{id: int, name: string, expected: int, found: int}>,
     *     mismatched: list<int>,
     *     unwritten: int,
     *     leftBehind: int
     * }
     */
    public function verify(): array
    {
        $models     = [];
        $mismatched = [];

        foreach ($this->db->query('SELECT id, name FROM hyper_models ORDER BY id')->getResultArray() as $model) {
            $expected = 0;
            $found    = 0;

            $this->eachChunk((int) $model['id'], function (array $rows) use (&$expected, &$found, &$mismatched): void {
                $expected += count($rows);

                $entries = $this->engine->getMany($this->tenantId, array_map('intval', array_column($rows, 'sd_entry_id')));
                $sources = [];
                foreach ($this->db->query('SELECT id, fields FROM entry_data WHERE id IN (' . implode(',', array_column($rows, 'current_revision_id')) . ')')->getResultArray() as $r) {
                    $sources[(int) $r['id']] = $r['fields'];
                }

                foreach ($rows as $row) {
                    $entry = $entries[(int) $row['sd_entry_id']] ?? null;
                    if ($entry === null) {
                        $mismatched[] = (int) $row['id'];

                        continue;
                    }
                    $found++;

                    $want = Payload::fieldsFor(Payload::convert($sources[(int) $row['current_revision_id']] ?? null));
                    if ($want === null || self::canonical($want) !== self::canonical($entry->fields)) {
                        $mismatched[] = (int) $row['id'];
                    }
                }
            });

            $models[] = ['id' => (int) $model['id'], 'name' => (string) $model['name'], 'expected' => $expected, 'found' => $found];
        }

        return [
            'models'     => $models,
            'mismatched' => $mismatched,
            'unwritten'  => (int) $this->db->query('SELECT COUNT(*) AS n FROM hyper_entries WHERE deleted_at IS NULL AND sd_entry_id IS NULL')->getRow()->n,
            'leftBehind' => (int) $this->db->query('SELECT COUNT(*) AS n FROM entries e WHERE e.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM hyper_entries h WHERE h.id = e.id)')->getRow()->n,
        ];
    }

    /**
     * Hand the live, written entries of one model to the callback in chunks that stay under 1,000 entries and the byte budget.
     *
     * @param callable(list<array{id: string, sd_entry_id: string, current_revision_id: string}>): void $callback
     */
    private function eachChunk(int $modelId, callable $callback): void
    {
        $lastId = 0;
        while (true) {
            $rows = $this->db->query(
                'SELECT id, sd_entry_id, current_revision_id, payload_bytes FROM hyper_entries
                WHERE model_id = ? AND deleted_at IS NULL AND sd_entry_id IS NOT NULL AND id > ? ORDER BY id ASC LIMIT 1000',
                [$modelId, $lastId]
            )->getResultArray();
            if ($rows === []) {
                return;
            }

            $take  = [];
            $bytes = 0;
            foreach ($rows as $row) {
                if ($take !== [] && $bytes + (int) $row['payload_bytes'] > Source::BATCH_BYTES) {
                    break;
                }
                $take[] = $row;
                $bytes += (int) $row['payload_bytes'];
            }

            $callback($take);
            $lastId = (int) end($take)['id'];
        }
    }

    /**
     * Sort the keys of every object and settle the number type, so two values compare by content.
     */
    private static function canonical(mixed $value): mixed
    {
        // MySQL keeps 2.0 as the integer 2, so a number with no fraction is compared as an integer.
        if (is_float($value) && is_finite($value) && $value === floor($value) && abs($value) < 9.0E15) {
            return (int) $value;
        }
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
