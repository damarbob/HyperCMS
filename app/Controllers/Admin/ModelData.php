<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class ModelData extends BaseController
{

    public function show($modelId)
    {

        $id = $modelId;

        /* Model */

        $modelResult = $this->modelsModel->getCustomBuilder()->where('id', $id)->limit(1)->get()->getResultArray();

        // Check if the model exists
        if (empty($modelResult))
            return redirect('admin/entries')->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $modelResult['name']]));

        $model = $modelResult[0]; // Assign the model

        /* End of model */

        $this->data['model'] = $model;
        $this->data['title'] = lang('Admin.modelxHistory', ['x' => $model['name']]);

        return view('admin/model_data', $this->data);
    }

    public function clearHistory($modelId = null)
    {
        // Check if the model ID is provided.
        if (empty($modelId)) {
            return $this->respond(lang('Admin.noModelFound'), statusCode: 400, success: false);
        }

        // Retrieve the newest record for this model.
        $newestRecord = $this->modelDataModel
            ->select('id')
            ->where('model_id', $modelId)
            ->orderBy('id', 'DESC') // Or order by 'created_at' if available.
            ->limit(1)
            ->get()
            ->getRow();

        if (empty($newestRecord)) {
            return $this->respond(lang('Admin.noHistoryFound'), statusCode: 404, success: false);
        }

        // Delete all history records for this model but exclude the newest one.
        $this->modelDataModel
            ->where('model_id', $modelId)
            ->where('id !=', $newestRecord->id)
            ->delete(purge: true);

        // Optionally, check if any rows were affected.
        $affected = $this->modelDataModel->db->affectedRows();
        $message = ($affected > 0)
            ? lang('Admin.historySuccessfullyCleared')
            : lang('Admin.noHistoryCleared');

        return $this->respond($message, 'admin/entries');
    }
}
