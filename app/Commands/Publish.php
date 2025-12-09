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

        /** @var \StarCore\Config\Star $star */
        $star = config('Star');

        CLI::write("{$hyper->appName} {$hyper->appVersion} - Publisher", 'green');
        CLI::write('');

        // Read the options.
        $force = array_key_exists('f', $params) || array_key_exists('force', $params) || CLI::getOption('f') !== null || CLI::getOption('force') !== null;
        $prod  = array_key_exists('p', $params) || array_key_exists('prod', $params)  || CLI::getOption('p') !== null  || CLI::getOption('prod') !== null;
        $dev   = array_key_exists('d', $params) || array_key_exists('dev', $params)   || CLI::getOption('d') !== null   || CLI::getOption('dev') !== null;
        $list  = array_key_exists('ls', $params) || array_key_exists('list', $params)  || CLI::getOption('ls') !== null || CLI::getOption('list') !== null;

        if ($list) {
            $activeModules = array_unique($star->getActiveModules());
            $activeDevModules = array_unique($star->getActiveDevModules());

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

        CLI::write('Environment: ' . ($dev ? 'development' : ($prod ? 'production' : ENVIRONMENT)));
        CLI::write('Force overwrite: ' . ($force ? 'Yes' : 'No'));

        // If a module name is provided as the first parameter, use it.
        $moduleParam = isset($params[0]) ? trim($params[0]) : '';

        // Build the list of modules:
        // Start with the active modules and active dev modules…
        $modulesList = array_merge(
            $star->getActiveModules(),
            $star->getActiveDevModules()
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

        // Loop through each module (or namespace) to publish its assets.
        foreach ($modulesList as $module) {
            // Determine the source assets folder.
            if (strcasecmp($module, 'App') === 0) {
                // For the App namespace, assume assets live at APPPATH/Views/Public/
                $assetsSource = APPPATH . 'Public' . DIRECTORY_SEPARATOR;
            } else {
                // For modules, pick the base folder depending on if the module is active or dev:
                if (in_array($module, $star->getActiveModules(), true)) {
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
            if (($dev || ENVIRONMENT !== 'production') && !$prod) {
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
