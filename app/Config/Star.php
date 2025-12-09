<?php

namespace Config;

use StarCore\Config\Star as StarConfig;

class Star extends StarConfig
{
    // Default modules as a comma-separated list.
    protected string $defaultActiveModules = 'UserManagement, PagingSystem';
}
