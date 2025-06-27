<?php

namespace Modules\Voltic;

use App\Services\HyperHooks;

log_message('info', 'Voltic module initialized');

HyperHooks::getInstance()->register(hook('Core.modules:init'), function () {
    /** @var \Voltic\Config\Voltic */
    $config = config('Voltic');

    log_message('info', "Voltic is initialized with API key {$config->apiKey} and API endpoint {$config->apiUrl}");
});

HyperHooks::getInstance()->register(hook('Backend.controller:menu:data'), function ($additionalMenu) {
    $additionalMenu[lang('Admin.ai')]['voltic'] = [
        'url' => base_url('admin/voltic'),
        'icon' => 'fa-solid fa-bolt',
        'text' => lang('Voltic.moduleName'),
        'hint' => lang('Voltic.moduleDescription'),
        'tooltip_content' => lang('Voltic.moduleName'),
        'tooltip_placement' => 'right',
    ];
    // dd($additionalMenu);
    return $additionalMenu;
});
