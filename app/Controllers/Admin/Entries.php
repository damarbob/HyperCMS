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

        /* Entry */

        $entriesResult = $this->entriesModel->getCustomBuilder()->where('id', $id)->limit(1)->get()->getResultArray();

        // Check if the entry exists
        if (!$entriesResult)
            return redirect('admin/entries')->with('error', lang('Admin.noEntryFoundWithIdx', ['x' => $id]));

        $entry = $entriesResult[0];

        /* End of entry */

        /* Model */

        $modelResult = $this->modelsModel->getCustomBuilder()->where('id', $entry['model_id'])->limit(1)->get()->getResultArray();

        // Check if the model exists
        if (empty($modelResult))
            return redirect('admin/entries')->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $modelResult['name']]));

        $model = $modelResult[0]; // Assign the model

        // dd($model);

        /* End of model */

        /* View data */
        // View data should be declared before filter hooks to allow modification

        $this->data['model'] = $model;
        // $this->data['fields'] = $fields;

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

        $hooks->register(hook('backend.view:entries:edit'), function () use ($entry) {
            return view_cell('EntriesFormCell', ['type' => 'edit', 'entry' => $entry]);
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

    public function delete($id = null)
    {
        /* Validation */

        // Retrieve an array of IDs from POST data
        $ids = $this->request->getPost('ids');

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
                return redirect('admin/entries')
                    ->with('error', lang('Admin.noEntrySelected'));
            }
        }

        /* End of validation */

        /* Action */

        // Get the current authenticated user's ID (using CI Shield)
        $deleterId = auth()->user()->id;

        // (Optional) Retrieve the entries before deletion – if needed later.
        $entries = $this->entriesModel
            ->getCustomBuilder()
            ->whereIn('id', $ids)
            ->get()
            ->getResultArray();

        // Update deleter_id for the entries before deletion.
        $this->entriesModel
            ->whereIn('id', $ids)
            ->set(['deleter_id' => $deleterId])
            ->update();

        // Update deleter_id for all related "entry_data" records.
        $this->entryDataModel
            ->whereIn('entry_id', $ids)
            ->set(['deleter_id' => $deleterId])
            ->update();

        // Delete all related "entry_data" records using whereIn for bulk deletion.
        $this->entryDataModel->whereIn('entry_id', $ids)->delete();

        // Bulk delete entries.
        $this->entriesModel->delete($ids);

        /* End of action */

        /* Response */

        // Build a success response.
        $successMessage = (count($entries) > 1) ? lang('Admin.entriesSuccessfullyDeleted') : lang('Admin.entryxSuccessfullyDeleted', ['x' => $entries[0]['model_name']]);

        // Check if the response should be in JSON.
        if (strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false || $this->request->isAJAX()) {
            return $this->response
                ->setStatusCode(200, $successMessage)
                ->setJSON(['success' => $successMessage]);
        } else {
            return redirect('admin/entries')->with('success', $successMessage);
        }

        /* End of response */
    }

    public function purgeDeleted()
    {

        /* Validation */

        $deleteableEntries = $this->entriesModel->onlyDeleted()->findAll();

        if (empty($deleteableEntries)) {
            return $this->response
                ->setStatusCode(400, lang('Admin.trashIsEmpty'));
        }

        /* End of validation */

        /* Action */

        // Delete all related "entry_data" records using whereIn for bulk deletion.
        $this->entryDataModel->purgeDeleted();

        // Bulk delete entries.
        $this->entriesModel->purgeDeleted();

        /* End of action */

        /* Response */

        // Build a success response.
        $successMessage = lang('Admin.entriesSuccessfullyDeleted');

        // Check if the response should be in JSON.
        if (strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false || $this->request->isAJAX()) {
            return $this->response
                ->setStatusCode(200)
                ->setJSON(['success' => $successMessage]);
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
                return redirect('admin/entries')->with('error', lang('Admin.noEntrySelected'));
            }
        }

        // List all entries
        $entries = $this->entriesModel
            ->withDeleted()
            ->whereIn('id', $ids)
            ->findAll();

        // List restorable entry ids
        $restorableEntryIds = [];

        // List unrestorable entry ids
        $unrestorableEntryIds = [];

        foreach ($entries as $entry) {
            $modelId = $entry['model_id'];

            $model = $this->modelsModel->where('id', $modelId)->findAll();

            // If the model exists, restore the entry
            if ($model) {
                $restorableEntryIds[] = $entry['id'];
            } else {
                $unrestorableEntryIds[] = $entry['id'];
            }
        }

        if (empty($restorableEntryIds) && $unrestorableEntryIds) {
            return $this->response
                ->setStatusCode(400, lang('Admin.noRestorableEntriesFound'));
        }

        /* End of validation */

        /* Action */

        // Get the deleted entries
        $entries = $this->entriesModel->getDeletedCustomBuilder()->whereIn('id', $ids)->get()->getResultArray();

        // For soft deletes, "restoring" means updating the deleted_at column to NULL.
        // Restore associated entry_data records.
        $this->entryDataModel
            ->withDeleted()
            ->whereIn('entry_id', $restorableEntryIds)
            ->set(['deleted_at' => null])
            ->update();

        // Restore the entries themselves.
        $this->entriesModel
            ->withDeleted()
            ->whereIn('id', $restorableEntryIds)
            ->set(['deleted_at' => null])
            ->update();

        /* End of action */

        /* Response */

        // Prepare a success message.
        $successMessage = (count($entries) > 1) ? lang('Admin.xentriesSuccessfullyRestored', ['x' => count($restorableEntryIds)]) : lang('Admin.entryxSuccessfullyRestored', ['x' => $entries[0]['model_name']]);

        // If unrestorable entries exist
        if (!empty($unrestorableEntryIds)) {
            $successMessage .= ' ' . lang('Admin.unableToRestorexEntries', ['x' => count($unrestorableEntryIds)]);
        }

        // Return a JSON response if requested, otherwise redirect back.
        if (strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false || $this->request->isAJAX()) {
            return $this->response
                ->setStatusCode(200)
                ->setJSON(['success' => $successMessage]);
        } else {
            return redirect()->back()->with('success', $successMessage);
        }

        /* End of response */
    }
}
