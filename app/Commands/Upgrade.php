<?php

namespace App\Commands;

use App\Fields\FieldTypes;
use App\Libraries\Upgrade\Convert\EntryValues;
use App\Libraries\Upgrade\Convert\ModelFields;
use App\Libraries\Upgrade\Converters;
use App\Libraries\Upgrade\CounterSchemaStore;
use App\Libraries\Upgrade\EntryCopier;
use App\Libraries\Upgrade\ModelCopier;
use App\Libraries\Upgrade\PayloadWriter;
use App\Libraries\Upgrade\Preflight;
use App\Libraries\Upgrade\Source;
use App\Libraries\Upgrade\Steps\Steps;
use App\Libraries\Upgrade\Verifier;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Config\HyperEngine;
use Throwable;

/**
 * Brings a database made by HyperCMS 0.1 or 0.2 onto the tables this version reads.
 *
 * The old layout keeps entries and models in `models`, `model_data`, `entries` and `entry_data`. This
 * version keeps the current fields of every entry in StarDust 0.3, and everything else (public ids,
 * authors, edit times, history, trash, form definitions) in the `hyper_` tables. The command reads the
 * old tables, copies them with the same ids, and writes each live entry's current fields to StarDust.
 * It leaves the old tables as they are.
 *
 * Every step skips finished work, so the command can be run again after a failure.
 */
class Upgrade extends BaseCommand
{
    /**
     * The row in `hyper_counters` that marks an upgrade as complete. `.docker/entrypoint.sh` reads it by this name.
     */
    private const FINISHED = 'upgrade_finished';

    protected $group       = 'Hyper';
    protected $name        = 'hyper:upgrade';
    protected $description = 'Upgrades a HyperCMS 0.1 or 0.2 database to the layout this version reads.';
    protected $usage       = 'hyper:upgrade [options]';
    protected $options     = [
        '--dry-run' => 'Report what the upgrade would meet, and change nothing.',
        '--yes'     => 'Skip the backup question. For scripts that made the backup themselves.',
    ];

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

        $name   = $db->getDatabase();
        $source = new Source($db);

        $missing = $source->missingTables();
        if ($missing !== []) {
            CLI::error("Database '$name' has no " . implode(', ', $missing) . ' table, so it is not a HyperCMS 0.1 or 0.2 database. For a new install run `php spark hyper:setup`.');

            return EXIT_ERROR;
        }

        try {
            $report = (new Preflight($db, $source, service('stardust')))->run();
        } catch (Throwable $e) {
            CLI::error('The database could not be read. ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write("Database '$name'", 'yellow');
        $blocked = $this->report($report);

        if ($dryRun) {
            CLI::newLine();
            $this->reportConversions($db);
            CLI::write('Dry run, nothing was changed.');

            return $blocked ? EXIT_ERROR : EXIT_SUCCESS;
        }

        if ($blocked) {
            CLI::newLine();
            CLI::error('The upgrade cannot start until the problem above is solved.');

            return EXIT_ERROR;
        }

        if (! $yes) {
            CLI::newLine();
            CLI::write('The upgrade adds tables to this database and leaves the old ones as they are.');
            $answer = CLI::prompt('Make a backup you know how to restore, then type the database name to go on');
            if (trim($answer) !== $name) {
                CLI::error('The name does not match. Nothing was changed.');

                return EXIT_ERROR;
            }
        }

        return $this->upgrade($db, $source, $report);
    }

    /**
     * Run the steps in order. Each one skips what an earlier run finished.
     */
    private function upgrade(BaseConnection $db, Source $source, array $report): int
    {
        $engine = service('stardust');
        $tenant = config(HyperEngine::class)->tenantId;

        try {
            CLI::newLine();
            $exit = $this->call('hyper:migrate', ['all' => null]);
            if ($exit !== null && $exit !== EXIT_SUCCESS) {
                CLI::error('The migrations failed, so nothing was copied.');

                return EXIT_ERROR;
            }
            $db->resetDataCache();

            // A rerun is unfinished until it reaches the end again, so a restart in between does not boot the app.
            $db->table('hyper_counters')->where('name', self::FINISHED)->delete();

            $models = new ModelCopier($db, $source, $engine, $tenant);

            $copied = $models->copy();
            CLI::write("Copied {$copied['models']} models and {$copied['revisions']} model versions.");

            $registered = $models->register();
            CLI::write("Registered $registered models in StarDust.");

            $entries   = new EntryCopier($db, $source);
            $revisions = $entries->copyRevisions();
            $added     = $entries->copyEntries();
            CLI::write("Copied $added entries and {$revisions['count']} entry versions.");
            if ($revisions['repeated'] !== []) {
                CLI::write(count($revisions['repeated']) . ' entry versions named one field id more than once, and the last value was kept. Version ids: ' . implode(', ', array_slice($revisions['repeated'], 0, 50)) . (count($revisions['repeated']) > 50 ? ' and more' : ''), 'yellow');
            }
            if ($revisions['odd'] !== []) {
                CLI::write(count($revisions['odd']) . ' entry versions hold a list in some other shape and were copied as text. Version ids: ' . implode(', ', array_slice($revisions['odd'], 0, 50)) . (count($revisions['odd']) > 50 ? ' and more' : ''), 'yellow');
            }

            $payloads = (new PayloadWriter($db, $engine, $tenant))->write();
            CLI::write("Wrote {$payloads['written']} entries to StarDust.");

            $types  = FieldTypes::core();
            $result = (new Verifier($db, $engine, $tenant, new EntryValues($types), new ModelFields($types)))->verify();
        } catch (Throwable $e) {
            CLI::newLine();
            CLI::error('The upgrade stopped. ' . $e->getMessage());
            CLI::error('Fix the cause and run the command again. Steps that finished are skipped.');

            return EXIT_ERROR;
        }

        // The conversions run on what was copied even when some entries could not be written, so the rows that are there are
        // in the new shape. The entries that were left out are converted when a rerun copies them.
        // Rows that were just copied, or written to StarDust again, are in the old shape, so the conversions go again
        // from the start. They change nothing that is already converted.
        $store = new CounterSchemaStore($db);
        if ($copied['models'] > 0 || $copied['revisions'] > 0 || $added > 0 || $revisions['count'] > 0 || $payloads['written'] > 0) {
            $store->setVersion(0);
            for ($step = 1; $step <= Steps::LATEST; $step++) {
                $store->clearCursor($step);
            }
        }

        try {
            foreach ((new Converters(Steps::make($db)))->run($store) as $step) {
                CLI::write("Step {$step['number']}, {$step['name']}, changed {$step['changed']}.");
                foreach (array_slice($step['problems'], 0, 50) as $problem) {
                    CLI::write("  $problem", 'yellow');
                }
                if (count($step['problems']) > 50) {
                    CLI::write('  and ' . (count($step['problems']) - 50) . ' more.', 'yellow');
                }
            }
        } catch (Throwable $e) {
            CLI::newLine();
            CLI::error('A conversion step stopped. ' . $e->getMessage());
            CLI::error('Fix the cause and run the command again. Steps that finished are skipped.');

            return EXIT_ERROR;
        }

        CLI::newLine();
        CLI::write('Entries per model, in the hyper tables and read back from StarDust:');
        foreach ($result['models'] as $model) {
            CLI::write(sprintf('  %d %s: %d written, %d read back', $model['id'], $model['name'], $model['expected'], $model['found']));
        }

        $failed = false;
        foreach ($payloads['skipped'] as $skip) {
            CLI::error("Entry {$skip['id']} was not written, because {$skip['reason']}");
            $failed = true;
        }
        if ($result['mismatched'] !== []) {
            CLI::error(count($result['mismatched']) . ' entries differ from their source or did not come back. Entry ids: ' . implode(', ', array_slice($result['mismatched'], 0, 50)) . (count($result['mismatched']) > 50 ? ' and more' : ''));
            $failed = true;
        }
        if ($result['unwritten'] > 0 && $payloads['skipped'] === []) {
            CLI::error("{$result['unwritten']} live entries still have no StarDust entry.");
            $failed = true;
        }
        if ($result['leftBehind'] > 0) {
            CLI::write("{$result['leftBehind']} live entries of the old tables were left behind, because they have no saved version or no model.", 'yellow');
        }

        if ($failed) {
            CLI::newLine();
            CLI::error('The upgrade finished with the problems above. The old tables are untouched, and running the command again moves what it can.');

            return EXIT_ERROR;
        }

        // The docker entrypoint starts the app and the tick only when this mark exists on an old database.
        $db->query('INSERT INTO hyper_counters (name, value) VALUES (?, 1) ON DUPLICATE KEY UPDATE value = 1', [self::FINISHED]);

        CLI::newLine();
        CLI::write('The upgrade is complete. The old tables were left as they are.', 'green');

        return EXIT_SUCCESS;
    }

    /**
     * Report what the pending conversion steps would change, without changing anything.
     *
     * The steps read the version 2 tables, so before the copy and the migrations there is nothing to count.
     */
    private function reportConversions(BaseConnection $db): void
    {
        $ready = $db->tableExists('hyper_counters') && $db->tableExists('hyper_models') && $db->fieldExists('label_field', 'hyper_models');
        if (! $ready) {
            CLI::write('The conversions of stored values run after the copy, so they are counted on the first real run.');

            return;
        }

        try {
            $store  = new CounterSchemaStore($db);
            $report = (new Converters(Steps::make($db)))->run($store, true);
        } catch (Throwable $e) {
            CLI::write('The conversions could not be counted. ' . $e->getMessage(), 'yellow');

            return;
        }

        if ($report === []) {
            CLI::write('No conversion is pending.');

            return;
        }

        foreach ($report as $step) {
            CLI::write("Step {$step['number']}, {$step['name']}, would change {$step['changed']}.");
            foreach (array_slice($step['problems'], 0, 50) as $problem) {
                CLI::write("  $problem", 'yellow');
            }
            if (count($step['problems']) > 50) {
                CLI::write('  and ' . (count($step['problems']) - 50) . ' more.', 'yellow');
            }
        }
    }

    /**
     * Print what the preflight found.
     *
     * @return bool True when something found stops the upgrade from starting.
     */
    private function report(array $r): bool
    {
        $blocked = false;

        CLI::write("  Layout found: HyperCMS {$r['layout']}");

        $server = $r['server'];
        CLI::write("  Database server: {$server['version']}" . ($server['supported'] ? '' : ', too old for StarDust 0.3'));
        if (! $server['supported']) {
            CLI::error('  ' . $server['message']);
            CLI::error('  StarDust 0.3 needs MySQL 8.0.13 or newer, or MariaDB 10.11 or newer.');
            $blocked = true;
        }

        $packet = $r['packet'];
        CLI::write(sprintf('  max_allowed_packet: %s, largest entry text: %s', self::bytes($packet['maxAllowed']), self::bytes($packet['largestPayload'])));
        if (! $packet['fits']) {
            CLI::write('  The largest entry text does not fit the packet size with a margin. Entries that do not fit are skipped and listed, and raising max_allowed_packet lets a rerun move them.', 'yellow');
        }

        CLI::write('  Entries per model (live and trashed):');
        foreach ($r['models'] as $model) {
            CLI::write(sprintf('    %d %s: %d live, %d trashed', $model['id'], $model['name'], $model['live'], $model['trashed']));
        }

        if ($r['entriesWithoutVersion'] > 0) {
            CLI::write("  Entries with no saved version, which the old version never showed and the upgrade leaves behind: {$r['entriesWithoutVersion']}", 'yellow');
        }
        if ($r['oddVersions'] !== []) {
            CLI::write('  Entry versions that hold a list in some other shape and are kept as text only: ' . self::ids($r['oddVersions']), 'yellow');
        }
        if ($r['notObjectVersions'] !== []) {
            CLI::write('  Entry versions whose text is not an object: ' . self::ids($r['notObjectVersions']), 'yellow');
        }
        if ($r['repeatedVersions'] !== []) {
            CLI::write('  Entry versions that name one field id more than once, where the last value is kept: ' . self::ids($r['repeatedVersions']), 'yellow');
        }

        foreach ($r['fieldProblems'] as $problem) {
            CLI::write("  Model {$problem['model']}: {$problem['problem']}", 'yellow');
        }

        if ($r['entryQueries'] !== []) {
            CLI::write('  Models with a form field whose data query reads the entries table, which keeps working only for a small set of queries:', 'yellow');
            foreach ($r['entryQueries'] as $query) {
                CLI::write("    {$query['model']}, field {$query['field']}");
            }
        }

        return $blocked;
    }

    /**
     * @param list<int> $ids
     */
    private static function ids(array $ids): string
    {
        return count($ids) . ' (ids ' . implode(', ', array_slice($ids, 0, 20)) . (count($ids) > 20 ? ' and more' : '') . ')';
    }

    private static function bytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB';
    }
}
