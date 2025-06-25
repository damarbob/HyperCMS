<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Models extends AdminController
{
    public function index(): string
    {
        $this->data['pageLength'] = service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 10;
        $this->data['title'] = lang('Admin.models');
        $this->data['links'] = [
            'new' => base_url('admin/models/new'),
            'edit' => base_url('admin/models') . '/{id}/edit', // The ID must be separated from the base URL to prevent it from being URL-encoded.
            'delete' => base_url('admin/models/delete'),
            'restore' => base_url('admin/models/restore'),
        ];

        /* Filters */

        $this->data = $this->hooks->filter(hook('Backend.controller:models:index:data'), $this->data);

        /* End of filters */

        return render('admin/models', $this->data);
    }

    public function new(): string
    {
        /** @var \Config\AuthGroups */
        $authGroups = config('AuthGroups');

        // Editable data
        $this->data = array_merge($this->data, [
            'title' => lang('Admin.newModel'),
        ]);

        $this->hooks->register(hook('Backend.view:models:new'), function () {
            return render('admin/partials/models_form', [
                'action' => 'new',
                'formAction' => base_url('admin/models'),
            ]);
        });

        return render(
            'admin/models_action',
            array_merge($this->data, [
                // Noneditable data
                'action' => 'new',
                'groups' => $authGroups->groups,
            ])
        );
    }

    public function edit($id)
    {
        /** @var \Config\AuthGroups */
        $authGroups = config('AuthGroups');

        // Editable data
        $this->data = array_merge($this->data, [
            'title' => lang('Admin.editModel'),
        ]);

        $model = $this->modelsManager->find($id);

        if (empty($model)) {
            return $this->respond(lang('Admin.modelNotFound'), 'admin/models', 400, success: false);
        }

        $this->hooks->register(hook('Backend.view:models:edit'), function () use ($model) {
            return render('admin/partials/models_form', [
                'action' => 'edit',
                'formAction' => base_url('admin/models/' . $model['id']),
                'model' => $model
            ]);
        });

        return render('admin/models_action', array_merge($this->data, [
            // Noneditable data
            'action' => 'edit',
            'model' => $model,
            'groups' => $authGroups->groups,
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
            'group' => 'permit_empty|min_length[3]|alpha_space',
            'user_groups' => 'required',
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

        // Dynamic conversion of array inputs
        // Loop through all validated fields. If any field is an array, convert it to JSON.
        foreach ($validData as $key => $value) {
            if (is_array($value)) {
                $validData[$key] = json_encode($value);
            }
        }

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
            'group' => 'permit_empty|min_length[3]|alpha_space',
            'user_groups' => 'required',
            'icon' => 'permit_empty',
        ];

        $data = $this->request->getPost(array_keys($rules));

        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        $validData = $this->validator->getValidated();
        $userId = auth()->user()->id;

        // Dynamic conversion of array inputs
        // Loop through all validated fields. If any field is an array, convert it to JSON.
        foreach ($validData as $key => $value) {
            if (is_array($value)) {
                $validData[$key] = json_encode($value);
            }
        }

        // dd($validData);

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
