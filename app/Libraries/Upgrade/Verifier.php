<?php

namespace App\Libraries\Upgrade;

use App\Libraries\Upgrade\Convert\EntryValues;
use App\Libraries\Upgrade\Convert\FileLinks;
use App\Libraries\Upgrade\Convert\ModelFields;
use App\Libraries\Upgrade\Steps\ConvertModelFields;
use CodeIgniter\Database\BaseConnection;
use StarDust\StarDust;

/**
 * Checks the result of the upgrade against the old tables, which it never changes.
 *
 * Two things must hold. For every model, the live entries in `hyper_entries` must all come back from
 * StarDust. And the fields StarDust returns for each live entry must equal that entry's current version
 * in `entry_data`, converted the way the upgrade converts it. The comparison is on the decoded values,
 * because MySQL rewrites the text of a JSON column, so key order and spacing differ and mean nothing.
 *
 * The conversion steps rewrite the values after the copy, so an entry that StarDust returns is accepted when it equals
 * the source as it was copied, or the source after the same conversion. The move of the files into the library changes
 * the values again, so the source after that is accepted too, with the files that the library holds by then.
 */
final class Verifier
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly StarDust $engine,
        private readonly int $tenantId,
        private readonly EntryValues $values,
        private readonly ModelFields $fields,
        private readonly ?FileLinks $links = null,
    ) {
    }

    /**
     * The library files by their path below the upload folder, read once.
     *
     * @var array<string, array{id: int, name: string}>|null
     */
    private ?array $library = null;

    /**
     * The library file that a path of the old upload form, or the path of an old link, names. It reads the library and
     * copies nothing.
     *
     * @return array{id: int, name: string}|null
     */
    private function libraryFile(string $path): ?array
    {
        if ($this->library === null) {
            $this->library = [];
            if ($this->db->tableExists('hyper_files')) {
                foreach ($this->db->query('SELECT id, path, name FROM hyper_files')->getResultArray() as $row) {
                    $this->library[(string) $row['path']] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
                }
            }
        }

        $path = (string) preg_replace('#^public/#', '', ltrim(str_replace('\\', '/', $path), '/'));

        return $this->library[str_starts_with($path, 'uploads/') ? substr($path, 8) : $path] ?? null;
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

        $all      = $this->db->query('SELECT id, name, fields FROM hyper_models ORDER BY id')->getResultArray();
        $modelIds = [];
        foreach ($all as $model) {
            $modelIds[(string) $model['name']] ??= (int) $model['id'];
        }

        foreach ($all as $model) {
            $expected   = 0;
            $found      = 0;
            $definition = $this->definitionOf($model['fields'], $modelIds);

            $this->eachChunk((int) $model['id'], function (array $rows) use (&$expected, &$found, &$mismatched, $definition): void {
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
                    if ($want === null) {
                        $mismatched[] = (int) $row['id'];

                        continue;
                    }

                    $got       = self::canonical($entry->fields);
                    $converted = $this->values->convert($want, $definition)['fields'];
                    if ($got !== self::canonical($want) && $got !== self::canonical($converted) && ! $this->matchesMoved($got, $converted, $definition)) {
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
     * Whether the values equal the converted source after its files were moved into the library.
     *
     * @param array<string, mixed>       $got
     * @param array<string, mixed>       $converted
     * @param list<array<string, mixed>> $definition
     */
    private function matchesMoved(array $got, array $converted, array $definition): bool
    {
        if ($this->links === null) {
            return false;
        }

        return $got === self::canonical($this->links->convert($converted, $definition, fn (string $path): ?array => $this->libraryFile($path))['fields']);
    }

    /**
     * The version 2 definition of a model, whether its stored definition has been converted yet or not.
     *
     * @param array<string, int> $modelIds
     *
     * @return list<array<string, mixed>>
     */
    private function definitionOf(?string $json, array $modelIds): array
    {
        $old = json_decode((string) $json, true);
        if (! is_array($old) || ! array_is_list($old)) {
            return [];
        }

        return ConvertModelFields::isVersion2($old) ? array_values(array_filter($old, 'is_array')) : $this->fields->convert($old, $modelIds)['fields'];
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
