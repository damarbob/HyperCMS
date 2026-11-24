<?php

namespace App\Commands;

use App\Libraries\TickRunner;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\HyperEngine;

/**
 * Runs one bounded pass of StarDust's background work, after a repair pass over unfinished writes.
 *
 * It finishes model deletions and any slot, index and import work StarDust has queued. Run it from
 * one cron line every minute, or from a loop in a container. Never run it beside StarDust's own
 * daemons, only one of the two. A host with no cron line can fetch the tick address instead, see
 * the `hyperengine.tickSecret` setting.
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

        $outcome = (new TickRunner())->run($budget);

        foreach ($outcome->lines as $line) {
            CLI::write($line);
        }
        foreach ($outcome->failures as $failure) {
            CLI::error($failure);
        }

        return $outcome->failed() ? EXIT_ERROR : EXIT_SUCCESS;
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
