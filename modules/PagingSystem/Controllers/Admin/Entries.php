<?php

namespace PagingSystem\Controllers\Admin;

use App\Controllers\AdminController;
use App\Services\HyperHooks;

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
            return $this->respond(lang('Admin.noModelFound'), success: false);

        $eligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');

        if (empty($eligibleModelIds) || !in_array($modelId, $eligibleModelIds)) {
            // log_message('debug', "Paging System: Model ID $modelId is not eligible for the page editor.");
            return redirect()->to("admin/model/$modelId/new");
        };

        $entry = $this->entriesModel
            ->getCustomBuilder()
            ->where('model_id', $modelId)
            // Where the fields is null, empty, or empty JSON
            ->groupStart()
            ->where('fields', null) // Null value
            ->orWhere('fields', "") // Empty string
            ->orWhere('fields', '""') // Empty string with double quotes
            ->orWhere('fields', "[]") // Empty JSON array
            ->orWhere('fields', "{}") // Empty JSON object
            ->orWhere('fields', "[{}]") // Empty JSON array with an empty object
            ->groupEnd()
            ->orderBy('date_modified', 'desc')
            ->get()
            ->getRowArray();

        // If the entry is not found, create a new one.
        // Otherwise, use the existing.
        if (!$entry) {
            $entryId = $this->entriesManager->create([
                "model_id" => $modelId,
                "fields" => "[]",
            ], auth()->user()->id);
        } else {
            $entryId = $entry['id'];
        }

        return redirect()->to("admin/model/$modelId/$entryId/edit");
    }
}
