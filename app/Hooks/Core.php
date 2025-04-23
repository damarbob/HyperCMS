<?php

use App\Entities\HyperHook;

return [

    // Hooks
    'modules:init' => new HyperHook('core:modules:init', 'Core Init Modules', ''),
];
