<?php

namespace App\Libraries\Upgrade;

use CodeIgniter\Database\BaseConnection;

/**
 * Reads the old tables, `models`, `model_data`, `entries` and `entry_data`, which both the 0.1 and the
 * 0.2 layout have. The 0.2 layout also has `current_model_data_id` and `current_entry_data_id`, which
 * name the current version. Nothing here writes.
 */
final class Source
{
    /**
     * How many rows are looked at at a time.
     */
    public const CHUNK = 500;

    /**
     * The most text read into memory at once. PHP in Docker stops at 128 MB, and one entry with
     * pictures pasted into its text can be several MB, so a count of rows is not a safe limit.
     */
    public const BATCH_BYTES = 8 * 1024 * 1024;

    public const TABLES = ['models', 'model_data', 'entries', 'entry_data'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * The tables of the old layout that this database is missing.
     *
     * @return list<string>
     */
    public function missingTables(): array
    {
        return array_values(array_filter(self::TABLES, fn (string $table): bool => ! $this->db->tableExists($table)));
    }

    /**
     * Which layout the old tables are in: '0.2' when they carry the current version columns, else '0.1'.
     */
    public function layout(): string
    {
        return $this->db->fieldExists('current_entry_data_id', 'entries') && $this->db->fieldExists('current_model_data_id', 'models') ? '0.2' : '0.1';
    }

    /**
     * A query with two columns, `owner_id` and `version_id`, that names the current version of every row of `$table`.
     *
     * A 0.2 database says which version it is. Where it does not, or on a 0.1 database, the rule HyperCMS
     * 0.1 used on every read applies: a live row takes its newest version that is not trashed, and a
     * trashed row takes its newest trashed version, because trashing a row trashes its versions with it.
     * A row whose versions are all trashed or all live falls back to its newest version.
     *
     * @param 'models'|'entries' $table
     */
    public function currentVersions(string $table): string
    {
        [$versions, $key, $column] = $table === 'models'
            ? ['model_data', 'model_id', 'current_model_data_id']
            : ['entry_data', 'entry_id', 'current_entry_data_id'];

        $pointer = $this->layout() === '0.2' ? "x.`$column`" : 'NULL';

        // One pass over the versions finds the newest of each kind for every row. A subquery per row would
        // read the versions once for every entry, and a 0.1 database has no index on the owner column.
        return "SELECT x.id AS owner_id, COALESCE({$pointer},
            CASE WHEN x.deleted_at IS NULL
                THEN COALESCE(g.live_id, g.any_id)
                ELSE COALESCE(g.trashed_id, g.any_id)
            END) AS version_id
            FROM `$table` x
            LEFT JOIN (
                SELECT v.`$key` AS owner_id,
                    MAX(CASE WHEN v.deleted_at IS NULL THEN v.id END) AS live_id,
                    MAX(CASE WHEN v.deleted_at IS NOT NULL THEN v.id END) AS trashed_id,
                    MAX(v.id) AS any_id
                FROM `$versions` v
                GROUP BY v.`$key`
            ) g ON g.owner_id = x.id";
    }

    /**
     * Hand every row of a table to the callback, a chunk at a time, in id order.
     *
     * Sizes are read first so that a run of rows with large text in them comes in small batches.
     *
     * @param list<string>                $columns The columns to read, besides `id`.
     * @param callable(array<string, ?string>): void $callback
     * @param string                      $sizeColumn The column whose length limits a batch.
     * @param string                      $where      An extra condition on the table, written as SQL.
     * @param float|null                  $deadline   A time from `microtime(true)`. The walk stops after the chunk that passes it.
     *
     * @return bool False when it stopped at the deadline, true when it reached the last row.
     */
    public function eachRow(string $table, array $columns, callable $callback, string $sizeColumn = 'fields', string $where = '1=1', int $after = 0, ?float $deadline = null): bool
    {
        $select = 'id, ' . implode(', ', array_map(static fn (string $c): string => "`$c`", $columns));
        $lastId = $after;

        while (true) {
            $sizes = $this->db->query("SELECT id, LENGTH(`$sizeColumn`) AS size FROM `$table` WHERE id > ? AND ($where) ORDER BY id ASC LIMIT " . self::CHUNK, [$lastId])->getResultArray();
            if ($sizes === []) {
                return true;
            }

            $ids   = [];
            $bytes = 0;
            foreach ($sizes as $row) {
                if ($ids !== [] && $bytes + (int) $row['size'] > self::BATCH_BYTES) {
                    break;
                }
                $ids[]  = (int) $row['id'];
                $bytes += (int) $row['size'];
            }

            foreach ($this->db->query("SELECT $select FROM `$table` WHERE id IN (" . implode(',', $ids) . ') ORDER BY id ASC')->getResultArray() as $row) {
                $callback($row);
            }
            $lastId = end($ids);

            if ($deadline !== null && microtime(true) >= $deadline) {
                return false;
            }
        }
    }
}
