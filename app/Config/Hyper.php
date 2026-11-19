<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Hyper extends BaseConfig
{
    public string $appName = 'Hyper CMS';
    public string $appVersion = '0.1.0-alpha.7';

    // Default modules as a comma-separated list.
    private string $defaultActiveModules = 'UserManagement, PagingSystem';

    // Active modules as a comma-separated list.
    public string $activeModules = '';

    // Active development modules as a comma-separated list.
    public string $activeDevModules = '';

    // If true, disables ALL modules.
    public bool $safeMode = false;

    // Ids of the models whose entries the API serves without a login.
    // The model form saves the real list in the settings table, and this is the value before any is saved.
    public array $publicModels = [];

    public function __construct()
    {
        parent::__construct();
        log_message('debug', 'HyperCMS Config loaded.');

        if (!$this->safeMode) {
            if (empty($this->activeModules)) {
                $this->activeModules = $this->defaultActiveModules;
            } else {
                // Merge default with custom active modules.
                $defaultModules = array_filter(array_map('trim', explode(',', $this->defaultActiveModules)));
                $customModules  = array_filter(array_map('trim', explode(',', $this->activeModules)));
                $mergedModules  = array_unique(array_merge($defaultModules, $customModules));
                $this->activeModules = implode(',', $mergedModules);
            }
        } else {
            // In safe mode, development modules are disabled.
            $this->activeDevModules = '';
        }
    }

    /**
     * Returns the active modules as an array.
     *
     * @return array The list of active module names.
     */
    public function getActiveModules(): array
    {
        return array_filter(array_map('trim', explode(',', $this->activeModules)));
    }

    /**
     * Returns the active development modules as an array.
     *
     * @return array The list of active development module names.
     */
    public function getActiveDevModules(): array
    {
        return array_filter(array_map('trim', explode(',', $this->activeDevModules)));
    }
}
