<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Publisher\Publisher;
use Throwable;

class Pull extends BaseCommand
{
    protected $group       = 'Hyper Publish';
    protected $name        = 'hyper:pull';
    protected $description = 'Pull assets from public folder back to modules';

    protected $usage   = 'hyper:pull [ModuleName] [options]';
    protected $options = [
        '-f, --force' => 'Overwrite existing module assets',
        '-d, --dev'   => 'Pull from development assets directory',
        '-ls, --list' => 'List modules with published assets',
    ];

    public function run(array $params)
    {
        /** @var \Config\Hyper $hyper */
        $hyper = config('Hyper');

        CLI::write("{$hyper->appName} {$hyper->appVersion} - Asset Pull", 'green');
        CLI::write('');

        $force = (bool) (CLI::getOption('force') ?? CLI::getOption('f'));
        $dev = (bool) (CLI::getOption('dev') ?? CLI::getOption('d'));
        $list = (bool) (CLI::getOption('list') ?? CLI::getOption('ls'));

        if ($list) {
            $modules = array_merge(
                $hyper->getActiveModules(),
                $hyper->getActiveDevModules(),
                ['App']
            );
            $modules = array_unique($modules);

            CLI::write('Pullable modules from ' . ($dev ? 'development' : 'production') . ':', 'white');
            foreach ($modules as $module) {
                $sourceBase = FCPATH . 'assets' . DIRECTORY_SEPARATOR
                    . ($dev ? '.hyper-dev' . DIRECTORY_SEPARATOR : '');

                $sourcePath = $module === 'App'
                    ? $sourceBase . 'App'
                    : $sourceBase . 'modules' . DIRECTORY_SEPARATOR . $module;

                $status = is_dir($sourcePath)
                    ? ' (pullable)'
                    : ' (not published in ' . ($dev ? 'dev' : 'prod') . ')';

                CLI::write($module . $status, is_dir($sourcePath) ? 'white' : 'yellow');
            }
            CLI::write('');
            return;
        }

        CLI::write('Source: ' . ($dev ? 'development' : 'production'), 'blue');
        CLI::write('Force overwrite: ' . ($force ? 'Yes' : 'No'));

        $moduleParam = $params[0] ?? null;
        $modules = array_merge(
            $hyper->getActiveModules(),
            $hyper->getActiveDevModules(),
            ['App']
        );
        $modules = array_unique($modules);

        if ($moduleParam) {
            $modules = array_filter($modules, fn($m) => strcasecmp($m, $moduleParam) === 0);
        }

        if (empty($modules)) {
            CLI::write('No matching modules found', 'red');
            return;
        }

        foreach ($modules as $module) {
            // 1. Determine source path
            $sourcePath = FCPATH . 'assets' . DIRECTORY_SEPARATOR;
            if ($dev) {
                $sourcePath .= '.hyper-dev' . DIRECTORY_SEPARATOR;
            }
            $sourcePath .= ($module === 'App')
                ? 'App' . DIRECTORY_SEPARATOR
                : 'modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR;

            if (!is_dir($sourcePath)) {
                CLI::write("• Skipped {$module}: No assets at {$sourcePath}");
                continue;
            }

            // 2. Determine target path
            if ($module === 'App') {
                $targetPath = APPPATH . 'Public' . DIRECTORY_SEPARATOR;
            } else {
                $activeModules = $hyper->getActiveModules();
                $devModules = $hyper->getActiveDevModules();
                $inBoth = in_array($module, $activeModules) && in_array($module, $devModules);

                if ($inBoth) {
                    $targetDev = $dev;
                    $moduleBase = $targetDev
                        ? MODULES_PATH . '.hyper-dev' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR
                        : MODULES_PATH . $module . DIRECTORY_SEPARATOR;
                    CLI::write("ℹ {$module} exists in both module sets - using " . ($targetDev ? 'dev' : 'prod'), 'cyan');
                } else {
                    $moduleBase = in_array($module, $activeModules)
                        ? MODULES_PATH . $module . DIRECTORY_SEPARATOR
                        : MODULES_PATH . '.hyper-dev' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR;
                }
                $targetPath = $moduleBase . 'Public' . DIRECTORY_SEPARATOR;
            }

            // 3. Ensure target directory exists
            if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true)) {
                CLI::write("✗ Failed to create {$targetPath}", 'red');
                continue;
            }

            // 4. Perform file copy
            try {
                $publisher = new Publisher($sourcePath, $targetPath);
                $publisher->addPaths(['.'])->merge($force);

                foreach ($publisher->getPublished() as $file) {
                    CLI::write('✓ ' . ($force ? 'Overwrote' : 'Copied') . " {$module}: {$file}", 'green');
                }
            } catch (Throwable $e) {
                $this->showError($e);
            }
        }
    }
}
