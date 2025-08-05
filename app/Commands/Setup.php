<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Handles the setup process for the application.
 */
class Setup extends BaseCommand
{
    protected $group = 'Hyper';
    protected $name = 'hyper:setup';
    protected $description = "Initial setup for Hyper CMS application.";

    protected $usage = 'hyper:setup';

    protected $envPath = ROOTPATH . '.env';

    public function run(array $params)
    {
        // Verify database connection
        $this->verifyDatabase();

        // Configure user registration
        $this->configureRegistration();

        // Run migrations with verification
        $this->runMigrations();

        // Publish assets
        $this->publishAssets();

        // Start server
        $this->startServer();
    }

    protected function verifyDatabase()
    {
        CLI::newLine();
        CLI::write('Verifying database connection...', 'yellow');

        try {
            // Attempt to connect to the database using the default group
            $db = Database::connect();
            $db->reconnect();

            CLI::write('✓ Database connection successful!', 'green');
            $db->close();
            return;
        } catch (Throwable $e) {
            CLI::error('Error message: ' . $e->getMessage());

            CLI::error('Setup cannot continue without database connection.');
            CLI::write('You can manually edit it at: ' . $this->envPath, 'yellow');
            CLI::write('Restart setup later with: php spark hyper:setup', 'yellow');
            exit(1);
        }
    }

    protected function configureRegistration()
    {
        CLI::newLine();
        $allow = CLI::prompt('Allow user registration? (yes|no)', 'yes');
        $value = (strtolower($allow) === 'yes') ? 'true' : 'false';

        $this->updateEnvValue('auth.allowRegistration', $value);

        if (strtolower($allow) === 'yes') {
            CLI::write('Important: Set auth.allowRegistration=false after creating your account', 'light_red');
        }
    }

    protected function runMigrations()
    {
        CLI::newLine();
        CLI::write('Running database migrations...', 'yellow');

        try {
            $exitCode = $this->call('hyper:migrate', ['all' => null]); // Use custom command
            CLI::write('✓ Database migrations completed', 'green');
        } catch (Throwable $e) {
            CLI::error('Migration failed: ' . $e->getMessage());
            CLI::error('Fix database issues and rerun setup');
            exit(1);
        }
    }

    protected function publishAssets()
    {
        CLI::write("\nPublishing vendor assets...", 'yellow');

        $actions = [
            ['components/jquery', 'nodejs' => null],
            ['stuk/jszip', 'nodejs' => null],
            ['bpampuch/pdfmake', 'nodejs' => null],
            ['datatables.net/datatables.net-bm', 'nodejs' => null, 'with-dependencies' => null],
            ['datatables.net/datatables.net-buttons-bm', 'nodejs' => null, 'with-dependencies' => null],
            ['datatables.net/datatables.net-colreorder-bm', 'nodejs' => null, 'with-dependencies' => null],
            ['datatables.net/datatables.net-fixedheader-bm', 'nodejs' => null, 'with-dependencies' => null],
            ['datatables.net/datatables.net-responsive-bm', 'nodejs' => null, 'with-dependencies' => null],
            ['datatables.net/datatables.net-select-bm', 'nodejs' => null, 'with-dependencies' => null],
            ['tinymce', 'nodejs' => null],
        ];

        foreach ($actions as $args) {
            try {
                $this->call('hyper:vendor-publish', $args);
                CLI::write("  ✓ Published: {$args[0]}", 'green');
            } catch (Throwable $e) {
                CLI::error("  Failed to publish {$args[0]}: " . $e->getMessage());
            }
        }

        CLI::write('✓ Assets publishing completed', 'green');
    }

    protected function startServer()
    {
        CLI::newLine(2);
        $start = CLI::prompt('Start development server now?', ['yes', 'no']);

        if (strtolower($start) === 'yes') {
            CLI::write('Starting development server...', 'yellow');
            $this->call('serve');
        } else {
            CLI::write('Start server later with: php spark serve', 'yellow');
        }
    }

    /**
     * Update or insert a key/value pair in the project’s .env file.
     *
     * @param string $key
     * @param string $value
     */
    protected function updateEnvValue(string $key, string $value)
    {
        $envPath = ROOTPATH . '.env';

        // Read existing .env (or create if missing)
        if (! is_file($envPath)) {
            file_put_contents($envPath, '');
        }
        $contents = file_get_contents($envPath);

        // Build the replacement line
        $newLine = "{$key} = {$value}";

        // Check for the key and replace or append
        $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*.*$/m';

        if (preg_match($pattern, $contents)) {
            // Replace the existing line
            $contents = preg_replace($pattern, $newLine, $contents);
        } else {
            // Append to end with a newline
            $contents = rtrim($contents, "\r\n") . "\n" . $newLine . "\n";
        }

        // Write back
        file_put_contents($envPath, $contents);
    }
}
