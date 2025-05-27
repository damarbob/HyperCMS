<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

/*
 * --------------------------------------------------------------------
 * Hyper Modules autoloading on pre_system
 * --------------------------------------------------------------------
 * Hyper Modules are loaded on the pre_system event.
 * This allows for modules to be loaded and hooks to be registered
 * before the system starts.
 */

Events::on('pre_system', function (): void {

    // Load the autoloader service and Hyper configuration.
    /** @var \CodeIgniter\Autoloader\Autoloader $autoloader */
    $autoloader = service('autoloader');
    /** @var \Config\Hyper $hyper */
    $hyper = config('Hyper');

    // Log safe mode as a string.
    log_message('info', 'Hyper safe mode: ' . ($hyper->safeMode ? 'true' : 'false'));

    // Helper closure to autoload modules.
    // $subFolder should be empty for regular modules, or e.g. '.hyper-dev' for development modules.
    $autoloadModules = function (array $modules, string $subFolder = '') use ($autoloader): void {
        foreach ($modules as $module) {
            // Skip any accidental dot entries.
            if ($module === '.' || $module === '..') {
                continue;
            }
            // Build the full module path.
            $modulePath = MODULES_PATH
                . (!empty($subFolder) ? $subFolder . DIRECTORY_SEPARATOR : '')
                . $module . DIRECTORY_SEPARATOR;
            // Register the module's namespace.
            $autoloader->addNamespace($module, $modulePath);

            // If an init file exists, require it.
            $initFile = $modulePath . 'init.php';
            if (file_exists($initFile)) {
                require_once $initFile;
            }
        }
    };

    // Autoload regular modules.
    $activeModules = $hyper->getActiveModules();
    log_message('info', 'Active Modules: ' . implode(', ', $activeModules));
    $autoloadModules($activeModules);

    // Autoload development modules.
    $activeDevModules = $hyper->getActiveDevModules();
    log_message('info', 'Active dev Modules: ' . implode(', ', $activeDevModules));
    $autoloadModules($activeDevModules, '.hyper-dev');

    // Display the namespaces added to the autoloader.
    log_message('info', 'Namespaces autoloaded: ' . implode(', ', array_keys($autoloader->getNamespace())));

    // Trigger module initialization hooks so that modules can register hooks on pre_system.
    service('hooks')->trigger(hook('Core.modules:init'));
});
