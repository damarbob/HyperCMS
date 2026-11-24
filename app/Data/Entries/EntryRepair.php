<?php

namespace App\Data\Entries;

use App\Data\ModelStore;
use App\Data\NamedLock;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use RuntimeException;
use Throwable;

/**
 * Finishes what a crash left halfway, so nobody has to remember to.
 *
 * A write changes HyperCMS rows, then StarDust, then HyperCMS rows again, and the process can die between
 * the steps. This pass looks for the three states that leaves:
 *
 * - A model with no StarDust model is registered.
 * - A live entry whose current version still holds a payload has it compared with StarDust. If StarDust
 *   matches, the copy is cleared. If not, the payload is written again.
 * - A trashed entry that still has a StarDust id has that StarDust row deleted.
 *
 * It takes the same named lock as a save, and leaves a row alone when a save holds it. `hyper:repair` runs it
 * over every row and `hyper:tick` over the rows older than a few minutes, so a save in progress is not
 * mistaken for a crash.
 */
class EntryRepair
{
    /**
     * How old a row must be before `hyper:tick` treats it as left over.
     */
    public const TICK_MIN_AGE_SECONDS = 180;

    private const BUSY = 'Another save of this record';

    public function __construct(
        private ?BaseConnection $db = null,
        private ?EntryWriter $writer = null,
        private ?ModelStore $models = null,
        private ?NamedLock $locks = null,
    ) {
    }

    /**
     * @param int $minAgeSeconds Leave alone rows touched less than this many seconds ago.
     *
     * @return array{registered: int, rewritten: int, cleared: int, deleted: int, busy: int, failed: array<string, string>}
     *         What was done, how many rows a save held, and the reason for each row that could not be finished.
     */
    public function run(int $minAgeSeconds = 0): array
    {
        $db     = $this->db();
        $cutoff = Time::now()->subSeconds(max(0, $minAgeSeconds))->toDateTimeString();
        $report = ['registered' => 0, 'rewritten' => 0, 'cleared' => 0, 'deleted' => 0, 'busy' => 0, 'failed' => []];

        // Models first, because an entry cannot be written to a model StarDust does not know.
        foreach ($db->query('SELECT id FROM ' . $db->prefixTable('hyper_models') . ' WHERE sd_model_id IS NULL AND updated_at <= ? ORDER BY id', [$cutoff])->getResultArray() as $row) {
            $id = (int) $row['id'];

            try {
                $this->locks()->run(NamedLock::forModel($id), function () use ($id): void {
                    $this->models()->ensureRegistered($id);
                });
                $report['registered']++;
            } catch (Throwable $e) {
                $this->fail($report, 'model ' . $id, $e);
            }
        }

        $entries = $db->query(
            'SELECT e.id FROM ' . $db->prefixTable('hyper_entries') . ' e LEFT JOIN ' . $db->prefixTable('hyper_entry_revisions') . ' r ON r.id = e.current_revision_id
            WHERE e.updated_at <= ?
              AND ((e.deleted_at IS NULL AND (e.sd_entry_id IS NULL OR r.fields IS NOT NULL))
                OR (e.deleted_at IS NOT NULL AND e.sd_entry_id IS NOT NULL))
            ORDER BY e.id',
            [$cutoff]
        )->getResultArray();

        foreach ($entries as $row) {
            $id = (int) $row['id'];

            try {
                $done = $this->writer()->complete($id);
                if ($done !== 'nothing') {
                    $report[$done]++;
                }
            } catch (Throwable $e) {
                $this->fail($report, 'entry ' . $id, $e);
            }
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function fail(array &$report, string $what, Throwable $e): void
    {
        if ($e instanceof RuntimeException && str_starts_with($e->getMessage(), self::BUSY)) {
            $report['busy']++;

            return;
        }

        log_message('error', 'Repair could not finish {what}. {message}', ['what' => $what, 'message' => $e->getMessage()]);
        $report['failed'][$what] = $e->getMessage();
    }

    private function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }

    /**
     * A save that is still running holds its lock for as long as it needs, so this waits a second and moves on.
     */
    private function locks(): NamedLock
    {
        return $this->locks ??= new NamedLock($this->db(), 1);
    }

    private function models(): ModelStore
    {
        return $this->models ??= new ModelStore($this->db());
    }

    private function writer(): EntryWriter
    {
        return $this->writer ??= new EntryWriter($this->db(), null, null, $this->locks(), $this->models());
    }
}
