<?php

use App\Hyper\HyperHook;

return [

    // Editor controller
    'controller:editor:index:data' => new HyperHook('pagingsystembackend:controller:editor:index:data', 'Data on Index Editor Paging System Controller', ''),

    // Frontend controller
    'controller:frontend:index' => new HyperHook('pagingsystembackend:controller:frontend:index', 'Index Frontend Paging System Controller', ''),
    'controller:frontend:index:pagehtml' => new HyperHook('pagingsystembackend:controller:frontend:index:pagehtml', 'Page HTML on Index Frontend Paging System Controller', ''),
];
