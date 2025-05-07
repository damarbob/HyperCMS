<?php

use App\Services\HyperHooks;

HyperHooks::getInstance()->register(hook('Backend.view:entries:edit'), function ($model, $entry) {
    $pagingSystemEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');

    $eligibility = false; // Initial state

    if (in_array($model['id'], $pagingSystemEligibleModelIds)) {
        $eligibility = true;
    }

    if ($eligibility):
        return view('Modules\PagingSystem\Views\Parts\button_open_editor', [
            'entry' => $entry,
        ]);
    endif;
});
