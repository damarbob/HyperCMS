<?php

use App\Services\HyperHooks;

// Sidebar Paging System settings menu
HyperHooks::getInstance()->register(hook('Backend.part:view:sidebar:settings'), function () {
    return view('Modules\PagingSystem\Views\Parts/menu_sidebar_settings');
});
