<?php

namespace App\Commands;

use App\Data\Entries\EntryRepair;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Finishes what a crash left halfway in a write to a model or an entry.
 *
 * A write changes HyperCMS rows, then StarDust, then HyperCMS rows again. If the process died between the
 * steps, this command compares the two sides and completes the write. It is safe to run at any time and as
 * often as needed, and `hyper:tick` runs the same pass on its own for rows older than a few minutes.
 */
class Repair extends BaseCommand
{
    protected $group       = 'Hyper';
    protected $name        = 'hyper:repair';
    protected $description = 'Finishes the writes a crash left halfway between HyperCMS and StarDust.';

    protected $usage   = 'hyper:repair [--min-age 0]';
    protected $options = [
        '--min-age' => 'Leave alone rows saved less than this many seconds ago, so a save in progress is not touched (defaults to 0)',
    ];

    public function run(array $params)
    {
        $minAge = $params['min-age'] ?? CLI::getOption('min-age') ?? $this->minAgeFromEquals() ?? 0;
        if (! is_numeric($minAge) || (int) $minAge < 0) {
            CLI::error('The minimum age must be a whole number of seconds, such as --min-age 300.');

            return EXIT_USER_INPUT;
        }

        try {
            $report = (new EntryRepair())->run((int) $minAge);
        } catch (Throwable $e) {
            CLI::error('The repair failed. ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(sprintf(
            'Repair finished. Registered %d model(s), wrote %d entry(ies) to StarDust again, cleared %d copy(ies) StarDust already held, deleted %d trashed entry(ies) from StarDust.',
            $report['registered'],
            $report['rewritten'],
            $report['cleared'],
            $report['deleted'],
        ));
        if ($report['busy'] > 0) {
            CLI::write(sprintf('%d row(s) were being saved and were left for the next run.', $report['busy']), 'yellow');
        }
        foreach ($report['failed'] as $what => $reason) {
            CLI::error("Could not finish $what. $reason");
        }

        return $report['failed'] === [] ? EXIT_SUCCESS : EXIT_ERROR;
    }

    /**
     * CodeIgniter reads `--min-age 300` but not `--min-age=300`, which is how most people write it,
     * so read that form from the raw arguments.
     */
    private function minAgeFromEquals(): ?string
    {
        foreach ($_SERVER['argv'] ?? [] as $argument) {
            if (str_starts_with((string) $argument, '--min-age=')) {
                return substr((string) $argument, 10);
            }
        }

        return null;
    }
}
