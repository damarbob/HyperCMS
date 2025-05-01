<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Models extends BaseController
{
    public function index(): string
    {
        // $this->modelsModel->test();

        $this->data['title'] = lang('Admin.models');

        return view('admin/models', $this->data);
    }

    public function new(): string
    {

        $this->data['title'] = lang('Admin.newModel');

        return view(
            'admin/models_action',
            array_merge($this->data, [
                'action' => 'new',
                'formAction' => base_url('admin/models'),
            ])
        );
    }

    public function edit($id): string
    {

        $this->data['title'] = lang('Admin.editModel');

        $modelBuilder = $this->modelsModel->getCustomBuilder();
        $model = $modelBuilder->where('id', $id)->limit(1)->get()->getResultArray()[0];
        $this->data['model'] = $model;

        return view('admin/models_action', array_merge($this->data, [
            'action' => 'edit',
            'formAction' => base_url('admin/models/' . $model['id']),
        ]));
    }

    public function create()
    {

        $rules = [
            'name' => 'required|min_length[3]|max_length[255]',
            'fields' => 'required',
            'icon' => ['permit_empty'],
        ];

        $data = $this->request->getPost(array_keys($rules));

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput();
        }

        // If you want to get the validated data.
        $validData = $this->validator->getValidated();

        // Data conversion for ModelsModel
        $modelsSubmitData = ['creator_id' => auth()->user()->id];

        // d($modelsSubmitData);
        // dd($modelDataSubmitData);

        // Data saving to ModelsModel
        $this->modelsModel->save($modelsSubmitData);

        // Data conversion for ModelDataModel
        $modelDataSubmitData = $data;
        $modelDataSubmitData['model_id'] = $this->modelsModel->getInsertID();
        $modelDataSubmitData['creator_id'] = auth()->user()->id;

        try {
            // Data saving to ModelDataModel
            $this->modelDataModel->save($modelDataSubmitData);
        } catch (DatabaseException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect('admin/models')->with('success', lang('Admin.modelxSuccessfullyCreated', ['x' => $data['name']]));
    }

    public function update($id)
    {

        $rules = [
            'name' => 'required|min_length[3]|max_length[255]',
            'fields' => 'required',
            'icon' => 'permit_empty',
        ];

        $data = $this->request->getPost(array_keys($rules));

        $modelBuilder = $this->modelsModel->getCustomBuilder();

        /** @todo Improve validation due to unusable is_unique as model_data may store the same data multiple times for historical tracking */
        // If user tries to change the name, prevent duplicate entry
        // $oldModel = $modelBuilder->where('id', $id)->limit(1)->get()->getResultArray()[0];
        // if ($oldModel['name'] != $data['name']) {
        //     $rules['name'] = $rules['name'] . '|is_unique[model_data.name]';
        // }

        // dd($this->validateData($data, $rules));

        if (!$this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('error', implode(" ", $this->validator->getErrors()));
        }

        // Data conversion for ModelsModel
        $modelsSubmitData = ['id' => $id];

        // Data saving to ModelsModel
        // $this->modelsModel->save($modelsSubmitData);

        // Data conversion for ModelDataModel
        $modelDataSubmitData = $data;
        $modelDataSubmitData['model_id'] = $id;
        $modelDataSubmitData['creator_id'] = auth()->user()->id;

        try {
            // Data saving to ModelDataModel
            $this->modelDataModel->save($modelDataSubmitData);
        } catch (DatabaseException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        // Redirect back to the edit page
        return redirect()->to("admin/models/$id/edit")->with('success', lang('Admin.modelxSuccessfullySaved', ['x' => $data['name']]));
    }

    public function delete($id = null)
    {
        // Retrieve an array of IDs from POST data
        $ids = $this->request->getPost('ids');

        /* Validation */

        // If no array was found but a single ID was passed in the URL, use that instead.
        if (empty($ids) && $id !== null) {
            $ids = [$id];
        }

        // If we still don't have any IDs, respond accordingly.
        if (empty($ids)) {
            // Check if the request expects a JSON response.
            if (strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false || $this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(400, lang('Admin.noEntrySelected'));
            } else {
                return redirect('admin/models')
                    ->with('error', lang('Admin.noEntrySelected'));
            }
        }

        /* End of validation */

        /* Action */

        // Get the current authenticated user's ID (using CI Shield)
        $deleterId = auth()->user()->id;

        // ---------------------------
        // Process Models and Model Data
        // ---------------------------

        // (Optional) Retrieve the models before deletion.
        $models = $this->modelsModel
            ->getCustomBuilder()
            ->whereIn('id', $ids)
            ->get()
            ->getResultArray();

        // Update deleter_id for the models that are about to be deleted.
        $this->modelsModel
            ->whereIn('id', $ids)
            ->set(['deleter_id' => $deleterId])
            ->update();

        // Update deleter_id for all related model_data records.
        $this->modelDataModel
            ->whereIn('model_id', $ids)
            ->set(['deleter_id' => $deleterId])
            ->update();

        // Delete all related model_data records.
        $this->modelDataModel->whereIn('model_id', $ids)->delete();

        // ---------------------------
        // Process Related Entries and Entry Data
        // ---------------------------

        // Retrieve all entry IDs from entriesModel that belong to the models being deleted.
        $entryResults = $this->entriesModel
            ->select('id')
            ->whereIn('model_id', $ids)
            ->findAll();

        // Extract the entry IDs into a simple array.
        $entryIds = array_column($entryResults, 'id');

        // If any entries exist, update and delete their entry_data records.
        if (!empty($entryIds)) {
            // Update deleter_id for all related entry_data records.
            $this->entryDataModel
                ->whereIn('entry_id', $entryIds)
                ->set(['deleter_id' => $deleterId])
                ->update();

            // Delete all related entry_data records.
            $this->entryDataModel->whereIn('entry_id', $entryIds)->delete();
        }

        // Update deleter_id for the entries that belong to the models being deleted.
        $this->entriesModel
            ->whereIn('model_id', $ids)
            ->set(['deleter_id' => $deleterId])
            ->update();

        // Bulk delete entries.
        $this->entriesModel->whereIn('model_id', $ids)->delete();

        // Finally, bulk delete models.
        $this->modelsModel->delete($ids);

        /* End of action */

        /* Response */

        // Build a success response.
        $successMessage = (count($models) > 1) ? lang('Admin.modelsSuccessfullyDeleted') : lang('Admin.modelxSuccessfullyDeleted', ['x' => $models[0]['name']]);

        // Check if the response should be in JSON.
        if (strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false || $this->request->isAJAX()) {
            return $this->response
                ->setStatusCode(200, $successMessage)
                ->setJSON(['success' => $successMessage]);;
        } else {
            return redirect('admin/models')->with('success', $successMessage);
        }

        /* End of response */
    }

    public function purgeDeleted()
    {
        /* Action */

        // Retrieve all deleted model IDs from modelsModel.
        $deletedModels = $this->modelsModel
            ->select('id')
            ->onlyDeleted()
            ->findAll();

        $deletedModelIds = array_column($deletedModels, 'id');

        if (empty($deletedModelIds)) {
            if (strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false || $this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(400, lang('Admin.trashIsEmpty'));
            } else {
                return redirect()->back()->with('error', lang('Admin.trashIsEmpty'));
            }
        }

        // Retrieve all entry IDs from entriesModel that belong to the models being deleted.
        $deletedEntries = $this->entriesModel
            ->select('id')
            ->whereIn('model_id', $deletedModelIds)
            ->findAll();

        // Extract the entry IDs into a simple array.
        $deletedEntryIds = array_column($deletedEntries, 'id');

        // Purge data

        // Purge all related model_data records using the retrieved model IDs.
        if (!empty($deletedModelIds)) {
            $this->modelDataModel->whereIn('model_id', $deletedModelIds)->delete(purge: true);
        }

        // Purge all related entry_data records using the retrieved entry IDs.
        if (!empty($deletedEntryIds)) {
            $this->entryDataModel->whereIn('entry_id', $deletedEntryIds)->delete(purge: true);
        }

        // Delete all related "entry_data" records using whereIn for bulk deletion.
        $this->modelDataModel->purgeDeleted();

        // Bulk delete entries.
        $this->modelsModel->purgeDeleted();

        /* End of action */

        /* Response */

        // Build a success response.
        $successMessage = lang('Admin.modelsSuccessfullyDeleted');

        // Check if the response should be in JSON.
        if (strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false || $this->request->isAJAX()) {
            return $this->response
                ->setStatusCode(200, $successMessage)
                ->setJSON(['success' => $successMessage]);;
        } else {
            return redirect()->back()->with('success', $successMessage);
        }

        /* End of response */
    }

    public function restore($id = null)
    {
        /* Validation */

        // Retrieve an array of IDs from POST data.
        $ids = $this->request->getPost('ids');

        // If no array was found but a single ID was passed in the URL, wrap it in an array.
        if (empty($ids) && $id !== null) {
            $ids = [$id];
        }

        // If we still don't have any IDs, respond with an error.
        if (empty($ids)) {
            if (strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false || $this->request->isAJAX()) {
                return $this->response
                    ->setStatusCode(400, lang('Admin.noEntrySelected'));
            } else {
                return redirect('admin/models')->with('error', lang('Admin.noEntrySelected'));
            }
        }

        /* End of validation */

        /* Action */

        // Get the deleted models
        $models = $this->modelsModel->getDeletedCustomBuilder()->whereIn('id', $ids)->get()->getResultArray();
        $entries = $this->entriesModel->getDeletedCustomBuilder()->whereIn('model_id', $ids)->get()->getResultArray();

        $entryIds = array_column($entries, 'id'); // Collect entry ids

        // If entries exist, restore the entry data as well
        if (!empty($entryIds)) {
            $this->entryDataModel->withDeleted()->whereIn('entry_id', $entryIds)
                ->set(['deleted_at' => null])
                ->update();
        }

        $this->entriesModel->withDeleted()->whereIn('model_id', $ids)
            ->set(['deleted_at' => null])
            ->update();

        // For soft deletes, "restoring" means updating the deleted_at column to NULL.
        // Restore associated model_data records.
        $this->modelDataModel->withDeleted()->whereIn('model_id', $ids)
            ->set(['deleted_at' => null])
            ->update();

        // Restore the models themselves.
        $this->modelsModel->withDeleted()->whereIn('id', $ids)
            ->set(['deleted_at' => null])
            ->update();

        /* End of action */

        /* Response */

        // Prepare a success message.
        $successMessage = (count($models) > 1) ? lang('Admin.modelsSuccessfullyRestored') : lang('Admin.modelxSuccessfullyRestored', ['x' => $models[0]['name']]);

        // Return a JSON response if requested, otherwise redirect back.
        if (strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false || $this->request->isAJAX()) {
            return $this->response
                ->setStatusCode(200, $successMessage)
                ->setJSON(['success' => $successMessage]);;
        } else {
            return redirect()->back()->with('success', $successMessage);
        }

        /* End of response */
    }
}
