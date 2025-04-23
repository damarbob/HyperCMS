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
            'name' => 'required|is_unique[model_data.name]|min_length[3]|max_length[255]',
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

    public function delete($id)
    {
        // Delete all model_data entries associated with this model
        $this->modelDataModel->where('model_id', $id)->delete();

        // Delete the model itself
        $this->modelsModel->delete($id);

        // Redirect with a success message
        return redirect('admin/models')->with('success', lang('Admin.modelxSuccessfullyDeleted', ['x' => $id]));
    }
}
