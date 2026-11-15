<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use stdClass;
use Throwable;

/**
 * Brings a database made by HyperCMS 0.1 up to the layout this version reads.
 *
 * HyperCMS 0.1 kept the data layer inside the app. This version uses the StarDust package, which
 * expects four things the old database does not have:
 *
 *  1. The table migration recorded under the `StarDust` namespace instead of `App`.
 *  2. The newer StarDust migrations, which add indexes and the current version columns.
 *  3. Entry fields saved as `{"title": "x"}` instead of `[{"id": "title", "value": "x"}]`.
 *  4. Each entry and model pointing at its current version.
 *
 * Every step leaves finished work alone, so the command can be run again after a failure.
 */
class Upgrade extends BaseCommand
{
    protected $group       = 'Hyper';
    protected $name        = 'hyper:upgrade';
    protected $description = 'Upgrades a HyperCMS 0.1 database to the layout this version reads.';
    protected $usage       = 'hyper:upgrade [options]';
    protected $options     = [
        '--dry-run' => 'Report what the upgrade would change, and change nothing.',
        '--yes'     => 'Skip the backup question. For scripts that made the backup themselves.',
    ];

    private const TABLES = ['migrations', 'models', 'model_data', 'entries', 'entry_data'];

    /**
     * The table migration as HyperCMS 0.1 recorded it, and as StarDust records it.
     */
    private const OLD_MIGRATION = 'App\Database\Migrations\CreateHyperTables';
    private const NEW_MIGRATION = 'StarDust\Database\Migrations\CreateHyperTables';

    /**
     * How many entry versions are read at a time.
     */
    private const CHUNK = 500;

    public function run(array $params)
    {
        $dryRun = array_key_exists('dry-run', $params) || CLI::getOption('dry-run') !== null;
        $yes    = array_key_exists('yes', $params) || CLI::getOption('yes') !== null;

        try {
            $db = Database::connect();
            $db->initialize();
        } catch (Throwable $e) {
            CLI::error('The database cannot be reached. ' . $e->getMessage());

            return EXIT_ERROR;
        }

        $name    = $db->getDatabase();
        $missing = array_values(array_filter(self::TABLES, static fn (string $table): bool => ! $db->tableExists($table)));
        if ($missing !== []) {
            CLI::error("Database '$name' has no " . implode(', ', $missing) . ' table, so it is not a HyperCMS database. For a new install run `php spark hyper:setup`.');

            return EXIT_ERROR;
        }

        $before = $this->inspect($db);
        CLI::write("Database '$name'", 'yellow');
        $this->report($before);

        if ($dryRun) {
            CLI::write('Dry run, nothing was changed. Pending migrations are not counted here and run during the upgrade.');

            return EXIT_SUCCESS;
        }

        if (! $yes) {
            CLI::newLine();
            CLI::write('The upgrade changes this database in place, and adding columns cannot be rolled back.');
            $answer = CLI::prompt('Make a backup you know how to restore, then type the database name to go on');
            if (trim($answer) !== $name) {
                CLI::error('The name does not match. Nothing was changed.');

                return EXIT_ERROR;
            }
        }

        try {
            $this->adoptHistory($db);

            CLI::newLine();
            $this->call('hyper:migrate', ['all' => null]);
            $db->resetDataCache();

            // The two data steps succeed or fail together.
            $db->transBegin();
            $odd    = $this->convertFields($db);
            $mapped = $this->mapCurrentVersions($db);
            $db->transCommit();

            $columns = $this->buildIndexes($db);
        } catch (Throwable $e) {
            $db->transRollback();
            CLI::newLine();
            CLI::error('The upgrade stopped. ' . $e->getMessage());
            CLI::error('Fix the cause and run the command again. Steps that finished are skipped.');

            return EXIT_ERROR;
        }

        $after = $this->inspect($db);

        CLI::newLine();
        CLI::write("Linked $mapped entries and models to their current version.");
        CLI::write("Sort columns on entry_data: {$columns['present']} present, {$columns['missing']} missing.");
        if ($columns['missing'] > 0) {
            CLI::write('The database refused some sort columns, and the reason is in writable/logs. StarDust builds them with MariaDB syntax. The site works without them, and sorting by a field is slower.', 'yellow');
        }

        $left = $after['unlinkedEntries'] + $after['unlinkedModels'] + $after['history'];
        if ($odd !== [] || $left > 0) {
            if ($odd !== []) {
                CLI::error(count($odd) . ' entry versions hold a list that is not in the old field format and were left as they are. entry_data ids: ' . implode(', ', array_slice($odd, 0, 50)) . (count($odd) > 50 ? ' and more' : ''));
            }
            if ($left > 0) {
                CLI::error('Some rows still need work.');
                $this->report($after);
            }

            return EXIT_ERROR;
        }

        CLI::write('The upgrade is complete.', 'green');

        return EXIT_SUCCESS;
    }

    /**
     * Count what is left to do. Nothing is written.
     *
     * @return array{history: int, oldFields: int, unlinkedEntries: int, unlinkedModels: int}
     */
    private function inspect(BaseConnection $db): array
    {
        $db->resetDataCache();

        $oldFields = 0;
        $this->eachVersion($db, static function (array $row) use (&$oldFields): void {
            if (self::keyValue($row['fields']) !== null) {
                $oldFields++;
            }
        });

        return [
            'history'         => $db->table('migrations')->where('class', self::OLD_MIGRATION)->countAllResults(),
            'oldFields'       => $oldFields,
            'unlinkedEntries' => $this->countUnlinked($db, 'entries', 'current_entry_data_id', 'entry_data', 'entry_id'),
            'unlinkedModels'  => $this->countUnlinked($db, 'models', 'current_model_data_id', 'model_data', 'model_id'),
        ];
    }

    /**
     * Count the rows that have at least one version and do not point at any.
     */
    private function countUnlinked(BaseConnection $db, string $table, string $column, string $versions, string $key): int
    {
        $builder = $db->table($table)->where("EXISTS (SELECT 1 FROM `$versions` WHERE `$versions`.`$key` = `$table`.`id`)", null, false);
        if ($db->fieldExists($column, $table)) {
            $builder->where("`$table`.`$column`", null);
        }

        return (int) $builder->countAllResults();
    }

    /**
     * @param array{history: int, oldFields: int, unlinkedEntries: int, unlinkedModels: int} $state
     */
    private function report(array $state): void
    {
        CLI::write("  Table migration recorded under the old name: {$state['history']}");
        CLI::write("  Entry versions in the old field format: {$state['oldFields']}");
        CLI::write("  Entries not linked to a current version: {$state['unlinkedEntries']}");
        CLI::write("  Models not linked to a current version: {$state['unlinkedModels']}");
    }

    /**
     * Record the table migration under StarDust's name, so `migrate` does not try to make the tables again.
     */
    private function adoptHistory(BaseConnection $db): void
    {
        $old = $db->table('migrations')->where('class', self::OLD_MIGRATION);

        if ($db->table('migrations')->where('class', self::NEW_MIGRATION)->countAllResults() > 0) {
            // A half-finished earlier attempt already made StarDust's row, so the old one is only clutter.
            $old->delete();

            return;
        }

        $old->update(['class' => self::NEW_MIGRATION, 'namespace' => 'StarDust']);
    }

    /**
     * Rewrite every entry version that is still in the old field format, trashed versions included.
     *
     * Only `fields` is written, so the date a version was last modified stays as it was.
     *
     * @return list<int> The ids of versions that hold a list in some other shape, which are left alone.
     */
    private function convertFields(BaseConnection $db): array
    {
        $converted = 0;
        $odd       = [];

        $this->eachVersion($db, static function (array $row) use ($db, &$converted, &$odd): void {
            $fields = self::keyValue($row['fields']);
            if ($fields === null) {
                return;
            }
            if ($fields === false) {
                $odd[] = (int) $row['id'];

                return;
            }

            // Text is kept unescaped, as the admin saves it, because search matches the stored text.
            $json = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            $db->table('entry_data')->where('id', $row['id'])->update(['fields' => $json]);
            $converted++;
        });

        CLI::newLine();
        CLI::write("Converted $converted entry versions to the new field format.");

        return $odd;
    }

    /**
     * Turn `[{"id": "title", "value": "x"}]` into `{"title": "x"}`.
     *
     * @return stdClass|false|null The new fields, false for a list in some other shape, or null when
     *                             there is nothing to convert (already converted, empty, or not JSON).
     */
    private static function keyValue(?string $raw): stdClass|false|null
    {
        $data = json_decode((string) $raw);
        if (! is_array($data) || $data === []) {
            return null;
        }

        $fields = new stdClass();
        foreach ($data as $item) {
            if (! $item instanceof stdClass || ! property_exists($item, 'value') || ! is_scalar($item->id ?? null)) {
                return false;
            }
            $fields->{(string) $item->id} = $item->value;
        }

        return $fields;
    }

    /**
     * Hand every row of `entry_data` to the callback, a chunk at a time, in id order.
     *
     * @param callable(array{id: string, fields: ?string}): void $callback
     */
    private function eachVersion(BaseConnection $db, callable $callback): void
    {
        $lastId = 0;
        do {
            $rows = $db->table('entry_data')->select('id, fields')->where('id >', $lastId)->orderBy('id', 'ASC')->limit(self::CHUNK)->get()->getResultArray();
            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                $callback($row);
            }
        } while ($rows !== []);
    }

    /**
     * Point each entry and model at its current version, the way HyperCMS 0.1 picked one on every read.
     *
     * A live row gets its newest version that is not trashed. A trashed row gets its newest trashed
     * version, because trashing a row trashes its versions with it. Rows that already point at a
     * version are left alone.
     *
     * @return int How many rows were linked.
     */
    private function mapCurrentVersions(BaseConnection $db): int
    {
        $linked = 0;
        $pairs  = [
            ['entries', 'current_entry_data_id', 'entry_data', 'entry_id'],
            ['models', 'current_model_data_id', 'model_data', 'model_id'],
        ];

        foreach ($pairs as [$table, $column, $versions, $key]) {
            $db->query("
                UPDATE `$table`
                INNER JOIN (
                    SELECT `$key`, MAX(id) AS version_id FROM `$versions` WHERE deleted_at IS NULL GROUP BY `$key`
                ) AS newest ON newest.`$key` = `$table`.id
                SET `$table`.`$column` = newest.version_id
                WHERE `$table`.`$column` IS NULL AND `$table`.deleted_at IS NULL
            ");
            $linked += $db->affectedRows();

            $db->query("
                UPDATE `$table`
                INNER JOIN (
                    SELECT `$key`, COALESCE(MAX(CASE WHEN deleted_at IS NOT NULL THEN id END), MAX(id)) AS version_id FROM `$versions` GROUP BY `$key`
                ) AS newest ON newest.`$key` = `$table`.id
                SET `$table`.`$column` = newest.version_id
                WHERE `$table`.`$column` IS NULL AND `$table`.deleted_at IS NOT NULL
            ");
            $linked += $db->affectedRows();
        }

        return $linked;
    }

    /**
     * Have StarDust build the sort columns for every model field, then count what the database really has.
     *
     * StarDust logs a column it could not make and carries on, so its own numbers cannot be trusted.
     *
     * @return array{present: int, missing: int}
     */
    private function buildIndexes(BaseConnection $db): array
    {
        $indexer  = \StarDust\Config\Services::runtimeIndexer();
        $existing = count($indexer->getAllVirtualColumns());
        $stats    = $indexer->indexAllModels();

        $db->resetDataCache();
        $present = count($indexer->getAllVirtualColumns());

        return ['present' => $present, 'missing' => max(0, $existing + (int) $stats['columns_created'] - $present)];
    }
}
