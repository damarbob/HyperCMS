<?php

namespace App\Commands;

use CodeIgniter\Commands\Database\Migrate as BaseMigrate;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Exceptions\DatabaseException;
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
        } catch (Throwable $e) {
            throw $e; // Re-throw to ensure CLI exits with error code
        }
    }
}
