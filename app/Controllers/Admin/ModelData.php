<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;

class ModelData extends AdminController
{

    public function show($modelId)
    {

        $id = $modelId;

        /* Model */

        $found = service('modelStore')->find($id);
        $modelResult = $found ? [(array) $found] : [];

        // Check if the model exists
        if (empty($modelResult))
            return redirect('admin/entries')->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $modelResult['name']]));

        $model = $modelResult[0]; // Assign the model

        /* End of model */

        $this->data['model'] = $model;
        $this->data['pageLength'] = service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 10;
        $this->data['title'] = lang('Admin.modelxHistory', ['x' => $model['name']]);

        return render('admin/model_data', $this->data);
    }

    public function clearHistory($modelId = null)
    {
        // Check if the model ID is provided.
        if (empty($modelId)) {
            return $this->respond(lang('Admin.noModelFound'), statusCode: 400, success: false);
        }

        // Delete all history records for this model but keep the newest one.
        $affected = service('modelStore')->clearHistory($modelId);

        if ($affected === null) {
            return $this->respond(lang('Admin.noHistoryFound'), statusCode: 404, success: false);
        }

        $message = ($affected > 0)
            ? lang('Admin.historySuccessfullyCleared')
            : lang('Admin.noHistoryCleared');

        return $this->respond($message, 'admin/entries');
    }
}
