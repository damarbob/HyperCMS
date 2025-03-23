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

        return view('admin/models_new', $this->data);
    }

    public function edit($id): string
    {

        $this->data['title'] = lang('Admin.editModel');

        $modelBuilder = $this->modelsModel->getCustomBuilder();
        $this->data['model'] = $modelBuilder->where('id', $id)->limit(1)->get()->getResultArray()[0];

        return view('admin/models_edit', $this->data);
    }

    public function create()
    {

        $rules = [
            'name' => 'required|is_unique[model_data.name]|min_length[3]|max_length[255]',
            'fields' => 'required',
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
        ];

        $data = $this->request->getPost(array_keys($rules));

        $modelBuilder = $this->modelsModel->getCustomBuilder();
        $oldModel = $modelBuilder->where('id', $id)->limit(1)->get()->getResultArray()[0];

        // If user tries to change the name, prevent duplicate entry
        if ($oldModel['name'] != $data['name']) {
            $rules['name'] = $rules['name'] . '|is_unique[model_data.name]';
        }

        // dd($this->validateData($data, $rules));

        if (!$this->validateData($data, $rules)) {
            return redirect()->back()->withInput();
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

        return redirect('admin/models')->with('success', lang('Admin.modelxSuccessfullySaved', ['x' => $data['name']]));
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
