<?php

namespace DataComparison;

use App\Services\HyperHooks;

log_message('info', 'Data Comparison module initialized');

HyperHooks::getInstance()->register(hook('Core.modules:init'), function () {});

HyperHooks::getInstance()->register(hook('Backend.controller:menu:data'), function ($additionalMenu) {
    // Main Data Comparison menu
    $additionalMenu[lang('Admin.data')]['data-comparison'] = [
        'url' => base_url('admin/data-comparison'),
        'icon' => 'fa-solid fa-table',
        'text' => lang('Dc.moduleName'),
        'tooltip_content' => lang('Dc.moduleName'),
        'tooltip_placement' => 'right',
    ];
    // Data Comparison settings submenu
    $additionalMenu[lang('Admin.others')]['settings']['submenu']['data-comparison'] = [
        'url' => base_url('admin/settings/data-comparison'),
        'text' => lang('Dc.moduleName'),
        'tooltip_content' => lang('Dc.moduleName'),
        'tooltip_placement' => 'right',
        'groups' => 'superadmin,admin,developer',
    ];
    return $additionalMenu;
});
