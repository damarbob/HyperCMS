<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Entries extends BaseController
{
    public function index(): string
    {
        // $this->modelsModel->test();

        $this->data['title'] = lang('Admin.entries');
        // $this->data['models'] = $this->modelsModel->get()->get()->getResultArray(); // Get all models (already did in the BaseController)

        return view('admin/entries', $this->data);
    }

    public function new()
    {

        $modelId = $this->request->getGet('model_id');
        $modelBuilder = $this->modelsModel->getCustomBuilder();
        $modelResult = $modelBuilder->where('id', $modelId)->limit(1)->get()->getResultArray();

        // Check if the model exists
        if (!$modelResult)
            return redirect('admin/entries')->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $modelId]));

        $model = $modelResult[0];

        $this->data['model'] = $model;

        $this->data['title'] = lang('Admin.newxEntry', ['x' => $model['name']]);

        return view('admin/entries_new', $this->data);
    }

    public function edit($id)
    {

        $modelName = $this->request->getGet('model_name');
        $modelBuilder = $this->modelsModel->getCustomBuilder();
        $modelResult = $modelBuilder->where('name', $modelName)->limit(1)->get()->getResultArray();

        // Check if the model exists
        if (!$modelResult)
            return redirect('admin/entries')->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $modelName]));

        $model = $modelResult[0];

        $this->data['model'] = $model;

        $builder = $this->entriesModel->getCustomBuilder();
        $entriesResult = $builder->where('id', $id)->limit(1)->get()->getResultArray();

        // Check if the model exists
        if (!$entriesResult)
            return redirect('admin/entries')->with('error', lang('Admin.noEntryFoundWithIdx', ['x' => $id]));

        $entry = $entriesResult[0];

        $this->data['entry'] = $entry;

        $this->data['title'] = lang('Admin.editxEntry', ['x' => $entry['model_name']]);

        // dd($entriesResult[0]);

        return view('admin/entries_edit', $this->data);
    }

    public function create()
    {

        $rules = [
            'model_id' => 'required',
            'fields' => 'required',
        ];

        $data = $this->request->getPost(array_keys($rules));

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput();
        }

        // Data conversion for EntriesModel
        $entriesSubmitData = [
            'model_id' => $data['model_id'],
            'creator_id' => auth()->user()->id
        ];

        // Data saving to EntriesModel
        $this->entriesModel->save($entriesSubmitData);

        // Data conversion for EntryDataModel
        $id = $this->entriesModel->getInsertID(); // Get id from last inserted entry
        $entryDataSubmitData['entry_id'] = $id;
        $entryDataSubmitData['fields'] = $data['fields'];
        $entryDataSubmitData['creator_id'] = auth()->user()->id;

        try {
            // Data saving to EntryDataModel
            $this->entryDataModel->save($entryDataSubmitData);
        } catch (DatabaseException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect('admin/entries')->with('success', lang('Admin.entryxSuccessfullyCreated', ['x' => $id]));
    }

    public function update($id)
    {

        dd($this->request->getPost());

        $rules = [
            'fields' => 'required',
        ];

        $data = $this->request->getPost(array_keys($rules));

        if (!$this->validateData($data, $rules)) {
            return redirect()->back()->withInput();
        }

        // Data conversion for EntryDataModel
        $entryDataSubmitData = $data;
        $entryDataSubmitData['entry_id'] = $id;
        $entryDataSubmitData['creator_id'] = auth()->user()->id;

        try {
            // Data saving to EntryDataModel
            $this->entryDataModel->save($entryDataSubmitData);
        } catch (DatabaseException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect('admin/entries')->with('success', lang('Admin.entryxSuccessfullySaved', ['x' => $id]));
    }

    public function delete($id)
    {
        // Delete all model_data entries associated with this model
        $this->entryDataModel->where('entry_id', $id)->delete();

        // Delete the model itself
        $this->entriesModel->delete($id);

        // Redirect with a success message
        return redirect('admin/entries')->with('success', lang('Admin.entryxSuccessfullyDeleted', ['x' => $id]));
    }
}
