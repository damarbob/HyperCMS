<?php

use StarCore\Service\HyperHooks;

// Sidebar Paging System settings menu
HyperHooks::getInstance()->register(hook('Backend.controller:menu:data'), function ($additionalMenu) {
    $additionalMenu[lang('Admin.others')]['settings']['submenu']['settings-paging-system'] = [
        'url' => base_url('admin/settings/paging-system'),
        'text' => lang('PagingSystem.moduleName'),
        'tooltip_content' => lang('PagingSystem.moduleName'),
        'tooltip_placement' => 'right',
        'groups' => 'superadmin,developer',
    ];
    return $additionalMenu;
});
