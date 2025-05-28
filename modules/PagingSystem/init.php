<?php

namespace Modules\PagingSystem;

use App\Services\HyperHooks;

log_message('debug', 'Paging System module initialized.');

// Loop through all PHP files in the /init folder
// and require them to initialize the module
foreach (glob(__DIR__ . '/init/*.php') as $file) {
    require_once $file;
}

HyperHooks::getInstance()->register(hook('Backend.view:entries:edit'), function ($model, $entry) {
    $pagingSystemEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');

    $eligibility = false; // Initial state

    if (in_array($model['id'], $pagingSystemEligibleModelIds)) {
        $eligibility = true;
    }

    if ($eligibility):
        return render('PagingSystem\Views\Parts\button_open_editor', [
            'entry' => $entry,
        ]);
    endif;
});

// Support for Custom Model Layuut module
HyperHooks::getInstance()->register(hook('Cml.view:entries:edit'), function ($model, $entry) {
    $pagingSystemEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');

    $eligibility = false; // Initial state

    if (in_array($model['id'], $pagingSystemEligibleModelIds)) {
        $eligibility = true;
    }

    if ($eligibility):
        return render('PagingSystem\Views\Parts\button_open_editor', [
            'entry' => $entry,
        ]);
    endif;
});

HyperHooks::getInstance()->register(hook('Backend.controller:model:index:data'), function ($data) {
    // Override default links
    $data['links']['new'] = base_url("admin/ps/entries/") . $data['id'] . '/new';
    return $data;
}, 1);

HyperHooks::getInstance()->register(hook('Backend.controller:entries:index:data'), function ($data) {
    // Override default links
    $data['links']['new'] = base_url("admin/ps/entries/") . '{id}/new';
    return $data;
}, 1);
