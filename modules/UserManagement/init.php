<?php

namespace DataComparison;

use App\Services\HyperHooks;

log_message('info', 'User management module initialized');

HyperHooks::getInstance()->register(hook('Core.modules:init'), function () {});

HyperHooks::getInstance()->register(hook('Backend.controller:menu:data'), function ($additionalMenu) {
    $additionalMenu[lang('Admin.others')]['user-management'] = [
        'url' => base_url('admin/users'),
        'icon' => 'fa-solid fa-person',
        'text' => lang('UserManagement.moduleName'),
        'hint' => lang('UserManagement.moduleDescription'),
        'tooltip_content' => lang('UserManagement.moduleName'),
        'tooltip_placement' => 'right',
        'groups' => 'superadmin,admin,developer'
    ];
    return $additionalMenu;
});
