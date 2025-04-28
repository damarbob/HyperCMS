<?php

namespace Config;

use CodeIgniter\Autoloader\Autoloader;
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
 * 
 */
Events::on('pre_system', function () {

    // Register module namespace to autoload
    /** @var \CodeIgniter\Autoloader\Autoloader */
    $autoloader = service('autoloader');

    // Autoload modules
    $activeModules = config(Hyper::class)->activeModules;
    log_message('info', 'Active Modules: ' . implode(', ', $activeModules));
    foreach ($activeModules as $module) {

        $autoloader->addNamespace(
            $module,
            MODULES_PATH . "{$module}/"
        );

        // If the module init exists, load it
        $initFile = MODULES_PATH . "{$module}/init.php";
        if (file_exists($initFile)) {
            require_once $initFile;
        }
    }

    // Autoload development modules
    $activeDevModules = config(Hyper::class)->activeDevModules;
    log_message('info', 'Active dev Modules: ' . implode(', ', $activeDevModules));
    foreach ($activeDevModules as $module) {

        $autoloader->addNamespace(
            $module,
            MODULES_PATH . ".hyper-dev/{$module}/"
        );

        // If the module init exists, load it
        $initFile = MODULES_PATH . ".hyper-dev/{$module}/init.php";
        if (file_exists($initFile)) {
            require_once $initFile;
        }
    }

    log_message('info', 'Namespaces autoloaded: ' . implode(', ', array_keys($autoloader->getNamespace())));

    // Autoload module routes
    // This is done to allow modules to register their own routes
    // without having to modify the main routes file.
    config(Hyper::class)->registerModuleRoutes();
    log_message('info', 'Module routes registered.');

    // Modules init hook
    // This is done to allow modules to register their own hooks on pre_system
    service('hooks')->trigger(hook('Core.modules:init'));
});
