<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Handles the setup process for the application.
 */
class Setup extends BaseCommand
{
    protected $group = 'Hyper';
    protected $name = 'hyper:setup';
    protected $description = "Setup application environment, database, migrations, and assets.";

    protected $usage = 'hyper:setup';

    public function run(array $params)
    {
        // Create .env file
        $createEnvFile = $this->createEnvFile();

        // Configure database settings if .env was created
        if ($createEnvFile) {
            $dbConfig = $this->configureDatabase();

            // Verify database connection
            $this->verifyDatabase($dbConfig);
        }

        // Configure user registration
        $this->configureRegistration();

        // Run migrations with verification
        $this->runMigrations();

        // Publish assets
        $this->publishAssets();

        // Start server
        $this->startServer();
    }

    protected function createEnvFile()
    {
        $envPath = ROOTPATH . '.env';
        $sourcePath = ROOTPATH . 'env';

        if (!file_exists($sourcePath)) {
            CLI::error('Source env file not found at: ' . $sourcePath);
            exit(1);
        }

        if (!file_exists($envPath)) {
            copy($sourcePath, $envPath);
            CLI::write('Created .env file from env', 'green');
        } else {
            CLI::write('.env file already exists cannot create', 'yellow');
            CLI::write('You can manually edit it at: ' . $envPath, 'yellow');
            return false;
        }

        return true;
    }

    protected function configureDatabase(): array
    {
        $configs = [
            'required' => [
                'database.default.hostname' => ['prompt' => 'Database hostname', 'default' => 'localhost'],
                'database.default.database' => ['prompt' => 'Database name', 'default' => 'hyper'],
                'database.default.username' => ['prompt' => 'Database username', 'default' => 'root'],
                'database.default.password' => ['prompt' => 'Database password', 'default' => 'root', 'secret' => true],
            ],
            'optional' => [
                'database.default.DBDriver' => ['prompt' => 'Database driver', 'default' => 'MySQLi'],
                'database.default.DBPrefix' => ['prompt' => 'Table prefix (leave blank for none)', 'default' => ''],
                'database.default.port' => ['prompt' => 'Database port', 'default' => '3306'],
            ]
        ];

        $envSettings = [];

        foreach ($configs as $type => $items) {
            CLI::newLine();
            CLI::write(strtoupper($type . ' DATABASE SETTINGS'), 'white', 'blue');

            foreach ($items as $key => $opts) {
                $default = $opts['default'];

                if (isset($opts['secret'])) {
                    // Corrected secret input handling
                    // $value = CLI::prompt($opts['prompt'], $default, ['mask' => '*']);
                    $value = CLI::prompt($opts['prompt'], $default);
                } else {
                    $value = CLI::prompt($opts['prompt'], $default);
                }

                $envSettings[$key] = $value;
                $this->updateEnvValue($key, $value);
            }
        }

        return $envSettings;
    }

    protected function verifyDatabase(array $dbConfig)
    {
        CLI::newLine();
        CLI::write('Verifying database connection...', 'yellow');

        // Prepare normalized config
        $config = [
            'hostname' => $dbConfig['database.default.hostname'],
            'database' => $dbConfig['database.default.database'],
            'username' => $dbConfig['database.default.username'],
            'password' => $dbConfig['database.default.password'],
            'DBDriver' => $dbConfig['database.default.DBDriver'] ?? 'MySQLi',
            // 'charset'  => 'utf8mb4',
            'port'     => is_numeric($dbConfig['database.default.port'] ?? null)
                ? (int)$dbConfig['database.default.port']
                : 3306,
        ];

        try {
            // Create connection directly without anonymous class
            $customConfig = new \Config\Database();
            $customConfig->default = $config;

            $db = \Config\Database::connect($config);
            $db->reconnect();

            CLI::write('✓ Database connection successful!', 'green');
            $db->close();
            return;
        } catch (Throwable $e) {
            // Create masked credentials for display
            $displayConfig = $config;
            $displayConfig['password'] = '******';

            CLI::error('Database connection failed with configuration:');
            CLI::print(json_encode($displayConfig, JSON_PRETTY_PRINT));
            CLI::error('Error message: ' . $e->getMessage());

            $this->updateDatabaseCredentials();
        }
    }

    protected function updateDatabaseCredentials()
    {
        CLI::newLine();
        $shouldUpdate = CLI::prompt('Update database settings?', ['yes', 'no']);

        if ($shouldUpdate === 'yes') {
            $dbConfig = $this->configureDatabase();
            $this->verifyDatabase($dbConfig);
            return;
        }

        CLI::error('Setup cannot continue without database connection');
        CLI::write('Restart setup later with: php spark hyper:setup', 'yellow');
        exit(1);
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
