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

        $this->hooks->register(hook('backend.view:models:new'), function () {
            return view_cell('ModelsFormCell', [
                'action' => 'new',
                'formAction' => base_url('admin/models'),
            ]);
        });

        return view(
            'admin/models_action',
            array_merge($this->data, [
                'action' => 'new',
            ])
        );
    }

    public function edit($id)
    {
        $this->data['title'] = lang('Admin.editModel');

        $model = $this->modelsManager->find($id);

        if (empty($model)) {
            return $this->respond(lang('Admin.modelNotFound'), 'admin/models', 400, success: false);
        }

        $this->hooks->register(hook('backend.view:models:edit'), function () use ($model) {
            return view_cell('ModelsFormCell', [
                'action' => 'edit',
                'formAction' => base_url('admin/models/' . $model['id']),
                'model' => $model
            ]);
        });

        return view('admin/models_action', array_merge($this->data, [
            'action' => 'edit',
            'model' => $model,
        ]));
    }

    /**
     * Create a new model.
     *
     * Validates input and delegates creation to the ModelManager.
     * 
     * @return \CodeIgniter\HTTP\Response JSON redirect or error response.
     */
    public function create()
    {
        // Define validation rules.
        $rules = [
            'name' => 'required|min_length[3]|max_length[255]',
            'fields' => 'required',
            'icon' => ['permit_empty'],
        ];

        // Get only the keys that are in the rules array.
        $data = $this->request->getPost(array_keys($rules));

        // Validate data – using CodeIgniter’s built-in validation.
        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        // Get the validated data.
        $validData = $this->validator->getValidated();
        $userId = auth()->user()->id; // Current authenticated user's ID

        try {
            // Delegate the creation process to the service.
            $modelId = $this->modelsManager->create($validData, $userId);
        } catch (DatabaseException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect('admin/models')
            ->with('success', lang('Admin.modelxSuccessfullyCreated', ['x' => $data['name']]));
    }

    public function update($id)
    {
        $rules = [
            'name' => 'required|min_length[3]|max_length[255]',
            'fields' => 'required',
            'icon' => 'permit_empty',
        ];

        $data = $this->request->getPost(array_keys($rules));

        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        $validData = $this->validator->getValidated();
        $userId = auth()->user()->id;

        try {
            $this->modelsManager->update($id, $validData, $userId);
        } catch (DatabaseException $e) {
            return $this->respond($e->getMessage(), withInput: true, success: false);
        }

        return $this->respond(lang('Admin.modelxSuccessfullySaved', ['x' => $validData['name']]), withInput: false);
    }

    public function delete($id = null)
    {
        $ids = $this->request->getPost('ids') ?: ($id ? [$id] : []);

        if (empty($ids)) {
            return $this->respond(lang('Admin.noEntrySelected'), statusCode: 400, success: false);
        }

        try {
            $this->modelsManager->deleteModels($ids, auth()->user()->id);
        } catch (DatabaseException $e) {
            return $this->respond($e->getMessage(), 'admin/models', 500, false, false);
        }

        $deletedModels = $this->modelsManager->findDeletedModels($ids);

        if (count($deletedModels) > 1) {
            return $this->respond(lang('Admin.modelsSuccessfullyDeleted'), 'admin/models');
        }

        return $this->respond(
            lang('Admin.modelxSuccessfullyDeleted', ['x' => $deletedModels[0]['name']]),
            'admin/models'
        );
    }

    public function purgeDeleted()
    {
        if ($this->modelsManager->countDeleted() == 0) {
            return $this->respond(lang('Admin.trashIsEmpty'), statusCode: 400, success: false);
        }

        try {
            $this->modelsManager->purgeDeleted();
        } catch (DatabaseException $e) {
            return $this->respond("{$e->getMessage()} {$e->getTraceAsString()}", statusCode: 500, success: false);
        }

        return $this->respond(lang('Admin.modelsSuccessfullyDeleted'));
    }

    public function restore($id = null)
    {
        $ids = $this->request->getPost('ids') ?: ($id ? [$id] : []);

        if (empty($ids)) {
            return $this->respond(lang('Admin.noEntrySelected'), statusCode: 400);
        }

        try {
            $this->modelsManager->restore($ids);
        } catch (DatabaseException $e) {
            return $this->respond($e->getMessage(), statusCode: 500);
        }

        return $this->respond(lang('Admin.modelsSuccessfullyRestored'), 200);
    }
}
