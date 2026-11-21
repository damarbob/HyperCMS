<?php

namespace App\Commands;

use App\Data\Entries\EntryRepair;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\HyperEngine;
use Throwable;

/**
 * Runs one bounded pass of StarDust's background work, after a repair pass over unfinished writes.
 *
 * It finishes model deletions and any slot, index and import work StarDust has queued. Run it from
 * one cron line every minute, or from a loop in a container. Never run it beside StarDust's own
 * daemons, only one of the two.
 */
class Tick extends BaseCommand
{
    protected $group       = 'Hyper';
    protected $name        = 'hyper:tick';
    protected $description = 'Runs one bounded pass of the background work StarDust has queued.';

    protected $usage     = 'hyper:tick [--budget 50]';
    protected $options   = [
        '--budget' => 'Seconds the run may spend before it stops (defaults to the tickBudgetSeconds setting)',
    ];

    public function run(array $params)
    {
        $budget = $params['budget'] ?? CLI::getOption('budget') ?? $this->budgetFromEquals();
        if ($budget !== null && (! is_numeric($budget) || (int) $budget < 1)) {
            CLI::error('The budget must be a whole number of seconds, such as --budget 50.');

            return EXIT_USER_INPUT;
        }
        $budget = $budget === null ? config(HyperEngine::class)->tickBudgetSeconds : (int) $budget;

        $exit = EXIT_SUCCESS;

        // First the repair pass, so a write a crash left halfway is finished within a minute or two. It only
        // looks at rows old enough that no save can still be running on them. A failure here must not stop
        // StarDust's own background work, so it is reported and the tick goes on.
        try {
            $repair = (new EntryRepair())->run(EntryRepair::TICK_MIN_AGE_SECONDS);
            $fixed  = $repair['registered'] + $repair['rewritten'] + $repair['cleared'] + $repair['deleted'];
            if ($fixed > 0) {
                CLI::write(sprintf(
                    'Repair finished %d unfinished write(s): %d model(s) registered, %d entry(ies) written again, %d copy(ies) cleared, %d trashed entry(ies) deleted.',
                    $fixed,
                    $repair['registered'],
                    $repair['rewritten'],
                    $repair['cleared'],
                    $repair['deleted'],
                ));
            }
            foreach ($repair['failed'] as $what => $reason) {
                CLI::error("Repair could not finish $what. $reason");
                $exit = EXIT_ERROR;
            }
        } catch (Throwable $e) {
            CLI::error('The repair pass failed. ' . $e->getMessage());
            $exit = EXIT_ERROR;
        }

        try {
            $report = service('stardust')->tick($budget);
        } catch (Throwable $e) {
            CLI::error('The tick failed. ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(sprintf(
            'Tick finished after %.1f of %d seconds in %d round(s), stopped because %s.',
            $report->elapsedSeconds,
            $report->budgetSeconds,
            $report->rounds,
            $report->stopReason->value,
        ));

        return $exit;
    }

    /**
     * CodeIgniter reads `--budget 50` but not `--budget=50`, which is how most people write it,
     * so read that form from the raw arguments.
     */
    private function budgetFromEquals(): ?string
    {
        foreach ($_SERVER['argv'] ?? [] as $argument) {
            if (str_starts_with((string) $argument, '--budget=')) {
                return substr((string) $argument, 9);
            }
        }

        return null;
    }
}
