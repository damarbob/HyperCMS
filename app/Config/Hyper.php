<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Hyper extends BaseConfig
{
    public string $appName = 'HyperCMS';
    public string $appVersion = '0.1.0-alpha.2';
    public array $activeModules = ['PagingSystem']; // Default modules
    public bool $safeMode = false; // Disables ALL modules if true

    public function __construct()
    {
        parent::__construct();
        $this->loadOverrides(); // Apply runtime overrides
        log_message('debug', 'HyperCMS Config loaded.');
    }

    /**
     * Dynamically activate a module (persists for current request).
     */
    public function activateModule(string $moduleName): bool
    {
        if ($this->safeMode) {
            log_message('warning', "Safe mode: Cannot activate module '{$moduleName}'.");
            return false;
        }

        if (!in_array($moduleName, $this->activeModules)) {
            $this->activeModules[] = $moduleName;
            log_message('info', "Module '{$moduleName}' activated.");
        }
        return true;
    }

    /**
     * Dynamically deactivate a module.
     */
    public function deactivateModule(string $moduleName): bool
    {
        $key = array_search($moduleName, $this->activeModules);
        if ($key !== false) {
            unset($this->activeModules[$key]);
            log_message('info', "Module '{$moduleName}' deactivated.");
        }
        return true;
    }

    /**
     * Load runtime overrides (e.g., from database or environment).
     */
    protected function loadOverrides(): void
    {
        // Example: Override active modules from ENV (comma-separated)
        if ($envModules = env('HYPER_ACTIVE_MODULES')) {
            $this->activeModules = array_merge(
                $this->activeModules,
                explode(',', $envModules)
            );
        }

        // Example: Enable safe mode via ENV
        $this->safeMode = (bool) env('HYPER_SAFE_MODE', false);
    }

    /**
     * Register module routes dynamically.
     */
    public function registerModuleRoutes()
    {

        foreach ($this->activeModules as $module) {
            if ($module === '.' || $module === '..') continue;

            $routesPath = MODULES_PATH . '/' . $module . '/Config/Routes.php';

            if (file_exists($routesPath)) {
                // Insert $routesPath at the beginning of the routeFiles array to allow override.
                array_unshift(config(Routing::class)->routeFiles, $routesPath);
            }
        }
    }
}
