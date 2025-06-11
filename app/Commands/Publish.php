<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Publisher\Publisher;
use Throwable;

class Publish extends BaseCommand
{
    protected $group       = 'Hyper Publish';
    protected $name        = 'hyper:publish';
    protected $description = 'Publish assets into the Hyper CMS.';

    protected $usage   = 'hyper:publish [ModuleName] [options]';
    protected $options = [
        '-f, --force' => 'Overwrite existing assets in the target directory',
        '-d, --dev'   => 'Force the environment to development mode',
        '-ls, --list' => 'List all publishable module assets',
    ];

    public function run(array $params)
    {
        /** @var \Config\Hyper $hyper */
        $hyper = config('Hyper');

        CLI::write("{$hyper->appName} {$hyper->appVersion} - Publisher", 'green');
        CLI::write('');

        // 1) Read the options.
        $force = (bool) (CLI::getOption('force') ?? CLI::getOption('f'));
        $dev = (bool) (CLI::getOption('dev') ?? CLI::getOption('d'));
        $list = (bool) (CLI::getOption('list') ?? CLI::getOption('ls'));

        if ($list) {
            $activeModules = array_unique($hyper->getActiveModules());
            $activeDevModules = array_unique($hyper->getActiveDevModules());

            CLI::write('Modules: ' . implode(', ', array_merge($activeModules, $activeDevModules)), 'blue');
            CLI::write('');

            CLI::write('Active modules:', 'white');
            foreach ($activeModules as $module) {
                $moduleBase = MODULES_PATH . $module . DIRECTORY_SEPARATOR;
                $assetsSource = $moduleBase
                    . 'Public' . DIRECTORY_SEPARATOR;
                CLI::write($module . (is_dir($assetsSource) ? ' (publishable)' : ''), (is_dir($assetsSource) ? 'white' : ''));
            }
            CLI::write('');

            CLI::write('Active dev modules:', 'white');
            foreach ($activeDevModules as $module) {
                $moduleBase = MODULES_PATH . '.hyper-dev' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR;
                $assetsSource = $moduleBase
                    . 'Public' . DIRECTORY_SEPARATOR;
                CLI::write($module . (is_dir($assetsSource) ? ' (publishable)' : ''), (is_dir($assetsSource) ? 'white' : ''));
            }
            CLI::write('');

            return;
        }

        CLI::write('Environment: ' . ($dev ? 'development' : ENVIRONMENT));
        CLI::write('Force overwrite: ' . ($force ? 'Yes' : 'No'));

        // 2) If a module name is provided as the first parameter, use it.
        $moduleParam = isset($params[0]) ? trim($params[0]) : '';

        // 3) Build the list of modules:
        //    Start with the active modules and active dev modules…
        $modulesList = array_merge(
            $hyper->getActiveModules(),
            $hyper->getActiveDevModules()
        );
        // ...and also include the App's namespace.
        $modulesList[] = 'App';

        // Remove duplicates.
        $modulesList = array_unique($modulesList);

        // If a specific module/namespace was provided, filter the list.
        if ($moduleParam !== '') {
            $modulesList = array_filter($modulesList, function ($module) use ($moduleParam) {
                return strcasecmp($module, $moduleParam) === 0;
            });
        }

        if (empty($modulesList)) {
            CLI::write('No modules found matching your criteria.', 'red');
            return;
        }

        CLI::write('Modules to publish: ' . implode(', ', $modulesList), 'blue');

        // 4) Loop through each module (or namespace) to publish its assets.
        foreach ($modulesList as $module) {
            // Determine the source assets folder.
            if (strcasecmp($module, 'App') === 0) {
                // For the App namespace, assume assets live at APPPATH/Views/Public/
                $assetsSource = APPPATH . 'Public' . DIRECTORY_SEPARATOR;
            } else {
                // For modules, pick the base folder depending on if the module is active or dev:
                if (in_array($module, $hyper->getActiveModules(), true)) {
                    $moduleBase = MODULES_PATH . $module . DIRECTORY_SEPARATOR;
                } else {
                    $moduleBase = MODULES_PATH . '.hyper-dev' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR;
                }
                $assetsSource = $moduleBase
                    . 'Public' . DIRECTORY_SEPARATOR;
            }

            // Skip if the source folder does not exist.
            if (! is_dir($assetsSource)) {
                CLI::write("• Skipped {$module}: no Public folder found at {$assetsSource}");
                continue;
            }

            // Determine the target path in the public folder.
            // In non-production environments, prefix with ".hyper-dev".
            $targetPath = FCPATH . 'assets' . DIRECTORY_SEPARATOR;

            // If dev option is activated or current environment is not production
            if ($dev || ENVIRONMENT !== 'production') {
                $targetPath .= '.hyper-dev' . DIRECTORY_SEPARATOR;
            }

            if (strcasecmp($module, 'App') === 0) {
                $targetPath .= $module; // public/assets/App
            } else {
                $targetPath .= 'modules' . DIRECTORY_SEPARATOR . $module;
            }

            // Create the target directory if it does not exist.
            if (! is_dir($targetPath)) {
                if (! mkdir($targetPath, 0755, true)) {
                    CLI::write("✗ Failed to create target directory: {$targetPath}", 'red');
                    continue;
                }
                CLI::write("✓ Created directory: {$targetPath}", 'white');
            }

            // Instantiate the Publisher with the source as assetsSource and destination as targetPath.
            $publisher = new Publisher($assetsSource, $targetPath);

            try {
                // Tell Publisher to add all files ('.') and merge.
                // The $force boolean controls whether existing files are overwritten.
                $publisher
                    ->addPaths(['.'])
                    ->merge($force);
            } catch (Throwable $e) {
                $this->showError($e);
                continue;
            }

            // Report published files.
            foreach ($publisher->getPublished() as $file) {
                CLI::write("✓ " . ($force ? 'Overwrote' : 'Copied') . " {$module} asset: {$file}", 'green');
            }
        }
    }
}
