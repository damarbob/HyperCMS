<?php

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Controllers\BaseController;
use App\Services\HyperHooks;
use App\Libraries\SyntaxProcessor;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Psr\Log\LoggerInterface;

class Entries extends BaseController
{

    protected SyntaxProcessor $syntaxProcessor;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        $this->syntaxProcessor = new SyntaxProcessor();
    }

    public function index(): string
    {
        // $this->modelsModel->test();

        $this->data['title'] = lang('Admin.entries');
        // $this->data['models'] = $this->modelsModel->get()->get()->getResultArray(); // Get all models (already did in the BaseController)

        return view('admin/entries', $this->data);
    }

    public function new()
    {
        /** @var HyperHooks $hooks */
        $hooks = service('hooks');

        $modelId = $this->request->getGet('model_id');
        $modelBuilder = $this->modelsModel->getCustomBuilder();
        $modelResult = $modelBuilder->where('id', $modelId)->limit(1)->get()->getResultArray();

        // Check if the model exists
        if (!$modelResult)
            return redirect('admin/entries')->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $modelId]));

        $model = $modelResult[0];

        $this->data['model'] = $model;

        // Process data syntax on model fields
        $this->data['processed_model_fields'] = $this->syntaxProcessor->process($model['fields']);

        $this->data['title'] = lang('Admin.newx', ['x' => $model['name']]);

        /* Register views */

        $hooks->register(hook('backend.view:entries:new'), function () use ($model) {
            return view_cell('EntriesFormCell', ['type' => 'new', 'model' => $model]);
        });

        /* End of register views */

        return view('admin/entries_new', $this->data);
    }

    public function edit($id)
    {

        /** @var HyperHooks $hooks */
        $hooks = service('hooks');

        $modelName = $this->request->getGet('model_name');

        /* Model */

        $modelBuilder = $this->modelsModel->getCustomBuilder();
        $modelResult = $modelBuilder->where('name', $modelName)->limit(1)->get()->getResultArray();

        // Check if the model exists
        if (!$modelResult)
            return redirect('admin/entries')->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $modelName]));

        $model = $modelResult[0]; // Assign the model

        /* End of model */

        /* Entry */

        $builder = $this->entriesModel->getCustomBuilder();
        $entriesResult = $builder->where('id', $id)->limit(1)->get()->getResultArray();

        // Check if the entry exists
        if (!$entriesResult)
            return redirect('admin/entries')->with('error', lang('Admin.noEntryFoundWithIdx', ['x' => $id]));

        $entry = $entriesResult[0];

        /* End of entry */

        /* View data */
        // View data should be declared before filter hooks to allow modification

        $this->data['model'] = $model;

        // Process data syntax on model fields
        $this->data['processed_model_fields'] = $this->syntaxProcessor->process($model['fields']);

        $this->data['entry'] = $entry;

        // Page title
        $this->data['title'] = lang('Admin.editx', ['x' => $entry['model_name']]);

        /* End of view data */

        /* Hooks */

        // Filtered data
        // Passes controller data to the hook
        $this->data = $hooks->filter(hook('backend.controller:entries:edit:data'), $this->data);

        // Hook for entry edit
        // Passes the model and entry data to the hook
        $hooks->trigger(hook('backend.controller:entries:edit'), [$this->data]);

        /* End of hooks */

        /* Register views */

        $hooks->register(hook('backend.view:entries:edit'), function () use ($model, $entry) {
            return view_cell('EntriesFormCell', ['type' => 'edit', 'entry' => $entry])
                . view_cell('EntriesHistoryCell', ['model' => $model]);
        });

        /* End of register views */

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
