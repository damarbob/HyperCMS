<?php

namespace App\Commands;

use CodeIgniter\Commands\Database\Migrate as BaseMigrate;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Exceptions\DatabaseException;
use StarDust\Exception\UnsupportedServerException;
use Throwable;

/**
 * Handles database migration operations for the application.
 * 
 * Extends the BaseMigrate class to provide custom migration logic.
 */
class Migrate extends BaseMigrate
{
    protected $group = 'Hyper';
    protected $name = 'hyper:migrate';
    protected $description = 'Runs migrations and exits with an error code on failure.';

    protected $usage = 'hyper:migrate [options]';
    protected $options = [
        '-n, --namespace' => 'Specify the namespace to migrate (defaults to App)',
        '-g, --group' => 'Database group name (as defined in Config/Database)',
        '--all' => 'Run migrations for all registered namespaces',
        '-h, --help' => 'Display this help message',
    ];

    public function run(array $params)
    {
        $runner = service('migrations');
        $runner->clearCliMessages();

        CLI::write(lang('Migrations.latest'), 'yellow');

        $namespace = $params['n'] ?? CLI::getOption('n');
        $group     = $params['g'] ?? CLI::getOption('g');

        try {
            // Handle namespace and group options
            if (array_key_exists('all', $params) || CLI::getOption('all')) {
                CLI::write('Running migrations for all namespaces', 'yellow');
                $runner->setNamespace(null);
            } elseif ($namespace) {
                $runner->setNamespace($namespace);
            }

            // Run migrations and check for success
            $success = $runner->latest($group);

            if (!$success) {
                CLI::error(lang('Migrations.generalFault'), 'light_gray', 'red');
                throw new DatabaseException(lang('Migrations.generalFault'));
            }

            // Output messages
            $messages = $runner->getCliMessages();
            foreach ($messages as $message) {
                CLI::write($message);
            }

            CLI::write(lang('Migrations.migrated'), 'green');

            $this->bootstrapEngine();
        } catch (Throwable $e) {
            throw $e; // Re-throw to ensure CLI exits with error code
        }
    }

    /**
     * Create StarDust's own tables next to the app's. It never runs on a request, only here and in
     * the upgrade command, and it is safe to run again on a database that already has them.
     */
    protected function bootstrapEngine(): void
    {
        CLI::write('Preparing the StarDust tables', 'yellow');

        try {
            service('stardust')->bootstrap();
        } catch (UnsupportedServerException $e) {
            CLI::error('This database server is too old for StarDust 0.3. It needs MySQL 8.0.13 or newer, or MariaDB 10.11 or newer.');
            CLI::error($e->getMessage());

            throw $e;
        }

        CLI::write('StarDust tables are ready', 'green');
    }
}
