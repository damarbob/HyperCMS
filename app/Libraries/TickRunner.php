<?php

namespace App\Libraries;

use App\Data\Entries\EntryRepair;
use CodeIgniter\Database\BaseConnection;
use Config\HyperEngine;
use Throwable;

/**
 * One bounded pass of the background work, shared by `php spark hyper:tick` and the tick address. It first finishes any write a
 * crash left halfway, then runs StarDust's own tick. A second run that starts while one is still going does nothing, so a
 * slow pass and the next scheduled call never overlap.
 */
final class TickRunner
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function run(int $budgetSeconds): TickOutcome
    {
        $outcome = new TickOutcome();
        $lock    = $this->lockName();

        if (! $this->acquire($lock)) {
            $outcome->lines[] = 'Another tick is still running, so this one did nothing.';

            return $outcome;
        }

        try {
            $this->repair($outcome);

            try {
                $report = service('stardust')->tick($budgetSeconds);
                $outcome->lines[] = sprintf(
                    'Tick finished after %.1f of %d seconds in %d round(s), stopped because %s.',
                    $report->elapsedSeconds,
                    $report->budgetSeconds,
                    $report->rounds,
                    $report->stopReason->value,
                );
                if ($report->stopReason->value === 'lock_contended') {
                    $outcome->lines[] = 'StarDust found another run holding its lock, such as its own daemons. Use either them or the tick, not both.';
                }
            } catch (Throwable $e) {
                $outcome->failures[] = 'The tick failed. ' . $e->getMessage();
                log_message('error', 'The tick failed. ' . $e->getMessage());
            }
        } finally {
            $this->release($lock);
        }

        return $outcome;
    }

    /**
     * The repair pass only looks at rows old enough that no save can still be running on them. A failure in it must not stop
     * StarDust's own work, so it is reported and the tick goes on.
     */
    private function repair(TickOutcome $outcome): void
    {
        try {
            $repair = (new EntryRepair())->run(EntryRepair::TICK_MIN_AGE_SECONDS);
            $fixed  = $repair['registered'] + $repair['rewritten'] + $repair['cleared'] + $repair['deleted'];
            if ($fixed > 0) {
                $outcome->lines[] = sprintf(
                    'Repair finished %d unfinished write(s): %d model(s) registered, %d entry(ies) written again, %d copy(ies) cleared, %d trashed entry(ies) deleted.',
                    $fixed,
                    $repair['registered'],
                    $repair['rewritten'],
                    $repair['cleared'],
                    $repair['deleted'],
                );
            }
            foreach ($repair['failed'] as $what => $reason) {
                $outcome->failures[] = "Repair could not finish $what. $reason";
            }
        } catch (Throwable $e) {
            $outcome->failures[] = 'The repair pass failed. ' . $e->getMessage();
        }
    }

    /**
     * MySQL scopes a lock name to the whole server, so the name carries the database and the table prefix of this site.
     */
    private function lockName(): string
    {
        return 'hypercms.tick.' . md5($this->db->getDatabase() . '|' . $this->db->getPrefix() . config(HyperEngine::class)->tablePrefix);
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
