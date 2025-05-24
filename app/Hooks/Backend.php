<?php

use App\Hyper\HyperHook;

return [

    // Hooks
    'view:models:new' => new HyperHook('backend:view:models:new', 'New Models View', ''),
    'view:models:edit' => new HyperHook('backend:view:models:edit', 'Edit Models View', ''),

    'controller:entries:edit' => new HyperHook('backend:controller:entries:edit', 'Edit Entries Controller', ''),
    'view:entries:new' => new HyperHook('backend:view:entries:new', 'New Entries View', ''),
    'view:entries:edit' => new HyperHook('backend:view:entries:edit', 'Edit Entries View', ''),

    'controller:settings' => new HyperHook('backend:controller:settings', 'Settings Controller', ''),
    'controller:settings:data' => new HyperHook('backend:controller:settings:data', 'Data on Settings Controller', ''),
    'controller:settings:update' => new HyperHook('backend:controller:settings:update', 'Update Settings Controller', ''),
    'view:settings' => new HyperHook('backend:view:settings', 'Settings View', ''),

    // Filters
    'controller:menu:data' => new HyperHook('backend:controller:menu:data', 'Menu Data on Base Controller', ''),

    'controller:model:index:data' => new HyperHook('backend:controller:model:index:data', 'Data on Index Model Controller', ''),

    'controller:entries:index:data' => new HyperHook('backend:controller:entries:index:data', 'Data on Index Entries Controller', ''),
    'controller:entries:edit:data' => new HyperHook('backend:controller:entries:edit:data', 'Data on Edit Entries Controller', ''),
];
