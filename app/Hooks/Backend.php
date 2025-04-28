<?php

use App\Entities\HyperHook;

return [

    // Hooks
    'part:view:sidebar:settings' => new HyperHook('backend:part:view:sidebar:settings', 'Settings Sidebar', ''),

    'controller:entries:edit' => new HyperHook('backend:controller:entries:edit', 'Edit Entries Controller', ''),
    'view:entries:new' => new HyperHook('backend:view:entries:new', 'New Entries View', ''),
    'view:entries:edit' => new HyperHook('backend:view:entries:edit', 'Edit Entries View', ''),

    'controller:settings' => new HyperHook('backend:controller:settings', 'Settings Controller', ''),
    'controller:settings:data' => new HyperHook('backend:controller:settings:data', 'Data on Settings Controller', ''),
    'controller:settings:update' => new HyperHook('backend:controller:settings:update', 'Update Settings Controller', ''),
    'view:settings' => new HyperHook('backend:view:settings', 'Settings View', ''),

    // Filters
    'controller:menu:data' => new HyperHook('backend:controller:menu:data', 'Menu Data on Base Controller', ''),
    'controller:entries:edit:data' => new HyperHook('backend:controller:entries:edit:data', 'Data on Edit Entries Controller', ''),
];
