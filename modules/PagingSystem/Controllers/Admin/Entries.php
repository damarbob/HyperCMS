<?php

namespace PagingSystem\Controllers\Admin;

use App\Controllers\AdminController;
use StarCore\Service\HyperHooks;

class Entries extends AdminController
{
    /**
     * This will use the last empty entry if exists.
     * Otherwise, create a new empty entry and then go to the edit page.
     */
    public function new($modelId)
    {
        // Check if the model ID is empty
        if (empty($modelId))
            return redirect('admin/entries')
                ->with('error', lang('Admin.noModelFound'));

        $eligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');

        // Check if the model ID is eligible for Paging System
        // If not eligible, redirect to the standard new entry page
        if (empty($eligibleModelIds) || !in_array($modelId, $eligibleModelIds)) {
            // log_message('debug', "Paging System: Model ID $modelId is not eligible for the page editor.");
            return redirect()->to("admin/model/$modelId/new");
        };

        // The newest entry of this model that has no field values yet.
        $entry = service('entryStore')->blankFor($modelId);

        // If the entry is not found, create a new one.
        // Otherwise, use the existing.
        if (!$entry) {
            $entryId = service('entryStore')->create([
                "model_id" => $modelId,
                "fields" => "[]",
            ], auth()->user()->id);
        } else {
            $entryId = $entry['id'];
        }

        return redirect()->to("admin/model/$modelId/$entryId/edit");
    }
}
