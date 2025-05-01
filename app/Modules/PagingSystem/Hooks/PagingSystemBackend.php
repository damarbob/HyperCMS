<?php

use App\Hyper\HyperHook;

return [

    // Hooks
    'controller:frontend:index' => new HyperHook('pagingsystembackend:controller:frontend:index', 'Index Frontend Paging System Controller', ''),
    'controller:frontend:index:pagehtml' => new HyperHook('pagingsystembackend:controller:frontend:index:pagehtml', 'Page HTML on Index Frontend Paging System Controller', ''),
];
