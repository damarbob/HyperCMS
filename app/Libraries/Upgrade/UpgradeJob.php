<?php

namespace App\Libraries\Upgrade;

use App\Libraries\Upgrade\Steps\Steps;
use CodeIgniter\Database\BaseConnection;
use Config\HyperEngine;
use StarDust\Exception\UnsupportedServerException;
use Throwable;

/**
 * The upgrade of an old layout, in pieces small enough for a web request. It runs the same copy and the same conversion steps as
 * `php spark hyper:upgrade`, in the same order, and keeps where it is in the progress file of `UpgradeState`, so each request
 * carries on from the last one. Every phase skips what an earlier call finished, so a request that is cut off loses nothing.
 *
 * It checks the result by counting, and does not compare every entry with its source as the command does. That comparison reads
 * the whole database twice and cannot be cut into requests, so a site with a shell should still run the command for it.
 */
final class UpgradeJob
{
    /**
     * The phases in order. `done` is the end.
     */
    public const PHASES = ['migrate', 'models', 'entries', 'payloads', 'convert', 'check', 'done'];

    /**
     * The most problem lines kept for one conversion step.
     */
    private const MAX_PROBLEMS = 100;

    public function __construct(private readonly BaseConnection $db, private readonly UpgradeState $state)
    {
    }

    /**
     * Where the upgrade is, or the state of one that has not begun.
     *
     * @return array{phase: string, tally: array<string, int>, skipped: array<string, string>, steps: array<int, array{number: int, name: string, changed: int, problems: list<string>}>, log: list<string>, failed: ?string, reset: bool, notes: array<string, int>}
     */
    public function progress(): array
    {
        return ($this->state->progress() ?? []) + self::fresh();
    }

    /**
     * Whether any work has been done, so the page shows progress and not the report.
     */
    public function started(): bool
    {
        return $this->state->progress() !== null;
    }

    /**
     * Begin again from the first phase, after a failure. Nothing already copied is lost, since every phase skips it.
     */
    public function restart(): void
    {
        $this->state->saveProgress(self::fresh());
    }

    /**
     * Do work until the deadline, then say where it stands.
     *
     * @param float $deadline A time from `microtime(true)`. A phase that is under way stops after the chunk that passes it.
     *
     * @return array<string, mixed> The progress, with `busy` set when another request holds the upgrade.
     */
    public function advance(float $deadline): array
    {
        $progress = $this->progress();
        if ($progress['phase'] === 'done' || $progress['failed'] !== null) {
            return $progress;
        }

        $lock = 'hypercms.upgrade.' . md5($this->db->getDatabase() . '|' . $this->db->getPrefix());
        if (! $this->acquire($lock)) {
            return $progress + ['busy' => true];
        }

        try {
            while ($progress['phase'] !== 'done' && microtime(true) < $deadline) {
                $before = $progress['phase'];
                $this->phase($progress, $deadline);
                $this->save($progress);

                if ($progress['failed'] !== null || $progress['phase'] === $before) {
                    break;
                }
            }
        } catch (Throwable $e) {
            $progress['failed'] = $e->getMessage();
            log_message('error', 'Upgrade stopped in phase ' . $progress['phase'] . '. ' . $e->getMessage());
        } finally {
            $this->release($lock);
        }

        $this->save($progress);

        return $progress;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fresh(): array
    {
        return [
            'phase'   => 'migrate',
            'tally'   => ['models' => 0, 'modelVersions' => 0, 'entryVersions' => 0, 'entries' => 0, 'payloads' => 0],
            'skipped' => [],
            'steps'   => [],
            'log'     => [],
            'failed'  => null,
            'reset'   => false,
            'notes'   => ['odd' => 0, 'repeated' => 0],
        ];
    }

    /**
     * @param array<string, mixed> $p
     */
    private function save(array $p): void
    {
        if ($p['phase'] === 'done') {
            // The page still shows this answer, and the next request finds the site current and has no page to show.
            $this->state->finish(Steps::LATEST);

            return;
        }

        $this->state->saveProgress($p);
    }

    /**
     * Run one phase, and move to the next when it is complete.
     *
     * @param array<string, mixed> $p
     */
    private function phase(array &$p, float $deadline): void
    {
        $engine = service('stardust');
        $tenant = (int) config(HyperEngine::class)->tenantId;

        switch ($p['phase']) {
            case 'migrate':
                $runner = service('migrations');
                $runner->setNamespace(null);
                if (! $runner->latest()) {
                    throw new \RuntimeException(lang('Upgrade.migrationsFailed') . ' ' . implode(' ', $runner->getCliMessages()));
                }

                try {
                    $engine->bootstrap();
                } catch (UnsupportedServerException $e) {
                    throw new \RuntimeException(lang('Upgrade.serverTooOld') . ' ' . $e->getMessage(), 0, $e);
                }

                $this->db->resetDataCache();
                // A run that starts again is unfinished until it reaches the end, so a restart in between does not boot the app.
                $this->db->table('hyper_counters')->where('name', UpgradeState::FINISHED)->delete();
                $p['log'][] = lang('Upgrade.logMigrated');
                $p['phase'] = 'models';

                return;

            case 'models':
                if (! $this->oldLayout()) {
                    $p['phase'] = 'convert';

                    return;
                }

                $models = new ModelCopier($this->db, new Source($this->db), $engine, $tenant);
                $copied = $models->copy();
                $p['tally']['models'] += $copied['models'];
                $p['tally']['modelVersions'] += $copied['revisions'];
                $registered = $models->register();
                $p['log'][] = lang('Upgrade.logModels', ['models' => $copied['models'], 'versions' => $copied['revisions'], 'registered' => $registered]);
                $p['phase'] = 'entries';

                return;

            case 'entries':
                $copier    = new EntryCopier($this->db, new Source($this->db));
                $revisions = $copier->copyRevisions($deadline);
                $p['tally']['entryVersions'] += $revisions['count'];
                $p['notes']['odd'] += count($revisions['odd']);
                $p['notes']['repeated'] += count($revisions['repeated']);
                if (! $revisions['complete']) {
                    return;
                }

                $p['tally']['entries'] += $copier->copyEntries();
                $p['log'][] = lang('Upgrade.logEntries', ['entries' => $p['tally']['entries'], 'versions' => $p['tally']['entryVersions']]);
                $p['phase'] = 'payloads';

                return;

            case 'payloads':
                $written = (new PayloadWriter($this->db, $engine, $tenant))->write($deadline);
                $p['tally']['payloads'] += $written['written'];
                foreach ($written['skipped'] as $skip) {
                    $p['skipped'][(string) $skip['id']] = $skip['reason'];
                }
                if (! $written['complete']) {
                    return;
                }

                $p['log'][] = lang('Upgrade.logPayloads', ['count' => $p['tally']['payloads']]);
                $p['phase'] = 'convert';

                return;

            case 'convert':
                $store = new CounterSchemaStore($this->db);

                // Rows that were just copied, or written to StarDust again, are in the old shape, so the conversions go again from the
                // start. They change nothing that is already converted.
                if (! $p['reset']) {
                    if (array_sum($p['tally']) > 0) {
                        $store->setVersion(0);
                        for ($step = 1; $step <= Steps::LATEST; $step++) {
                            $store->clearCursor($step);
                        }
                    }
                    $p['reset'] = true;
                }

                $complete = true;
                foreach ((new Converters(Steps::make($this->db)))->run($store, false, $deadline) as $step) {
                    $kept = $p['steps'][$step['number']] ?? ['number' => $step['number'], 'name' => $step['name'], 'changed' => 0, 'problems' => []];

                    $kept['changed'] += $step['changed'];
                    $kept['problems'] = array_slice(array_merge($kept['problems'], $step['problems']), 0, self::MAX_PROBLEMS);
                    $p['steps'][$step['number']] = $kept;

                    $complete = ($step['complete'] ?? true) !== false;
                }
                if (! $complete) {
                    return;
                }

                $p['phase'] = 'check';

                return;

            case 'check':
                $this->check($p);

                return;
        }
    }

    /**
     * Count what is left and finish, or fail with what is wrong. The finished mark is written only on success.
     *
     * @param array<string, mixed> $p
     */
    private function check(array &$p): void
    {
        $hyper     = $this->db->prefixTable('hyper_entries');
        $unwritten = (int) $this->db->query('SELECT COUNT(*) AS n FROM ' . $hyper . ' WHERE deleted_at IS NULL AND sd_entry_id IS NULL')->getRow()->n;
        $left      = $this->oldLayout()
            ? (int) $this->db->query('SELECT COUNT(*) AS n FROM entries e WHERE e.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM ' . $hyper . ' h WHERE h.id = e.id)')->getRow()->n
            : 0;

        if ($left > 0) {
            $p['log'][] = lang('Upgrade.logLeftBehind', ['count' => $left]);
        }

        if ($p['skipped'] !== []) {
            $p['failed'] = lang('Upgrade.failedSkipped', ['count' => count($p['skipped'])]);

            return;
        }
        if ($unwritten > 0) {
            $p['failed'] = lang('Upgrade.failedUnwritten', ['count' => $unwritten]);

            return;
        }

        // The docker entrypoint starts the app and the tick only when this mark exists on an old database.
        $this->db->query('INSERT INTO ' . $this->db->prefixTable('hyper_counters') . ' (name, value) VALUES (?, 1) ON DUPLICATE KEY UPDATE value = 1', [UpgradeState::FINISHED]);

        $p['log'][] = lang('Upgrade.logDone');
        $p['phase'] = 'done';
    }

    /**
     * Whether the old tables are there. A site that is only behind on conversions has none, and has nothing to copy.
     */
    private function oldLayout(): bool
    {
        return (new Source($this->db))->missingTables() === [];
    }

    private function acquire(string $name): bool
    {
        $row = $this->db->query('SELECT GET_LOCK(?, 0) AS got', [$name])->getRow();

        return $row !== null && (int) $row->got === 1;
    }

    private function release(string $name): void
    {
        try {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$name]);
        } catch (Throwable) {
            // The lock goes with the connection anyway.
        }
    }
}
