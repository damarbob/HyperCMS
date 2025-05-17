<?php

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Controllers\BaseController;
use App\Libraries\SyntaxProcessor;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\Files\UploadedFile;
use Psr\Log\LoggerInterface;

class Entries extends BaseController
{

    protected SyntaxProcessor $syntaxProcessor;
    protected string $uploadDestination = '.hyper-dev' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        $this->syntaxProcessor = syntax_processor();
    }

    public function index(): string
    {
        $this->data['pageLength'] = service('settings')->get('App.datatableEntriesPerPage', ('user:' . user_id())) ?: 10;
        $this->data['title'] = lang('Admin.entries');
        $this->data['links'] = [
            'new' => base_url('admin/entries/new?model_id=') . '{id}', // The ID must be separated from the base URL to prevent it from being URL-encoded.
            'edit' => base_url('admin/entries/') . '{id}/edit',
            'delete' => base_url('admin/entries/delete'),
            'restore' => base_url('admin/entries/restore'),
            'purge' => base_url('admin/entries/purge-deleted'),
        ];

        /* Filters */

        $this->data = $this->hooks->filter(hook('Backend.controller:entries:index:data'), $this->data);

        /* End of filters */

        return render('admin/entries', $this->data);
    }

    public function new()
    {
        $hooks = $this->hooks;
        $action = 'new';

        $modelId = $this->request->getGet('model_id');

        // Check if the model ID is empty
        if (empty($modelId))
            return $this->respond(lang('Admin.noModelFound'), success: false);

        $model = $this->modelsManager->find($modelId);

        // Check if the model does not exist
        if (!$model)
            return $this->respond(lang('Admin.noModelFoundWithIdx', ['x' => $modelId]), success: false);

        $this->data['model'] = $model;
        $this->data['processed_model_fields'] = $this->syntaxProcessor->process($model['fields']); // Process data syntax on the model fields
        $this->data['title'] = lang('Admin.newx', ['x' => $model['name']]);
        $this->data['type'] = $action;

        /* Register views */

        $hooks->register(hook('Backend.view:entries:new'), function () use ($model, $action) {
            return render('admin/partials/entries_form', [
                'action' => $action,
                'formAction' => base_url('admin/entries'),
                'model' => $model
            ]);
        });

        /* End of register views */

        return render('admin/entries_action', array_merge($this->data, [
            'action' => 'new'
        ]));
    }

    public function edit($id)
    {
        $hooks = $this->hooks;
        $action = 'edit';

        /* Entry */

        $entry = $this->entriesManager->find($id);

        // Check if the entry exists
        if (!$entry)
            return $this->respond(lang('Admin.noEntryFoundWithIdx', ['x' => $id]), success: false);

        /* End of entry */

        /* Model */

        $model = $this->modelsManager->find($entry['model_id']);

        // Check if the model exists
        if (empty($model))
            return $this->respond(lang('Admin.noModelFoundWithIdx', ['x' => $model['name']]), success: false);

        /* End of model */

        /* View data */
        // View data should be declared before filter hooks to allow modification

        $this->data['model'] = $model;
        $this->data['processed_model_fields'] = $this->syntaxProcessor->process($model['fields']); // Process data syntax on model fields
        $this->data['entry'] = $entry;

        // Page title
        $this->data['title'] = lang('Admin.editx', ['x' => $entry['model_name']]);
        // $this->data['type'] = $action;

        /* End of view data */

        /* Hooks */

        // Filtered data
        // Passes controller data to the hook
        $this->data = $hooks->filter(hook('Backend.controller:entries:edit:data'), $this->data);

        // Hook for entry edit
        // Passes the model and entry data to the hook
        $hooks->trigger(hook('Backend.controller:entries:edit'), [$this->data]);

        /* End of hooks */

        /* Register views */

        $hooks->register(hook('Backend.view:entries:edit'), function () use ($entry, $action) {
            return render('admin/partials/entries_form', [
                'action' => $action,
                'formAction' => base_url('admin/entries/' . $entry['id']),
                'entry' => $entry
            ]);
        });
        // dd(array_merge($this->data, [
        //     'action' => 'edit'
        // ]));

        /* End of register views */

        return render('admin/entries_action', array_merge($this->data, [
            'action' => 'edit'
        ]));
    }

    public function create()
    {

        $rules = [
            'model_id' => 'required',
            'fields' => 'required',
        ];

        $data = $this->request->getPost(array_keys($rules));

        // Validate data – using CodeIgniter’s built-in validation.
        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        // Get the validated data.
        $validData = $this->validator->getValidated();
        $userId = auth()->user()->id;

        // Process file uploads. This returns an array of file URLs and a log array.
        list($fileUrls, $fileLog) = $this->processUploadedFiles();

        if (!empty($fileLog['file_errors'])) {

            $fileErrors = [];
            foreach ($fileLog['file_errors'] as $error) {
                array_push($fileErrors, $error['error_message']);
            }

            return $this->respond(implode(' ', $fileErrors), success: false);
        }

        try {

            // Prepare final data
            $finalData = $validData;

            // Update meta fields with any file URLs.
            $finalData['fields'] = $this->updateMetaFields($validData['fields'], $fileUrls);

            // Delegate the creation process to the service
            $entryId = $this->entriesManager->create($finalData, $userId);
        } catch (DatabaseException $e) {
            return $this->respond($e->getMessage(), statusCode: 500, success: false);
        }

        return $this->respond(lang('Admin.entryxSuccessfullyCreated', ['x' => $entryId]), 'admin/entries');
    }

    public function update($id)
    {

        $rules = [
            'fields' => 'required',
        ];

        $data = $this->request->getPost(array_keys($rules));

        // Validate data – using CodeIgniter’s built-in validation.
        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        // Get the validated data.
        $validData = $this->validator->getValidated();
        $userId = auth()->user()->id;

        // Process file uploads. This returns an array of file URLs and a log array.
        list($fileUrls, $fileLog) = $this->processUploadedFiles();

        if (!empty($fileLog['file_errors'])) {

            $fileErrors = [];
            foreach ($fileLog['file_errors'] as $error) {
                array_push($fileErrors, $error['error_message']);
            }

            return $this->respond(implode(' ', $fileErrors), success: false);
        }

        try {

            // Prepare final data
            $finalData = $validData;

            // Update meta fields with any file URLs.
            $finalData['fields'] = $this->updateMetaFields($validData['fields'], $fileUrls);

            // Delegate the creation process to the service
            $this->entriesManager->update($id, $finalData, $userId);
        } catch (DatabaseException $e) {
            return $this->respond($e->getMessage(), statusCode: 500, success: false);
        }

        return $this->respond(lang('Admin.entryxSuccessfullySaved', ['x' => $id]), 'admin/entries');
    }

    public function delete($id = null)
    {
        /* Validation */

        // Retrieve an array of IDs from POST data
        $ids = $this->request->getPost('ids') ?: ($id ? [$id] : []);

        // If we still don't have any IDs, respond accordingly.
        if (empty($ids)) {
            return $this->respond(lang('Admin.noEntrySelected'), statusCode: 400, success: false);
        }

        /* End of validation */

        /* Action */

        // Get the current authenticated user's ID (using CI Shield)
        $deleterId = auth()->user()->id;

        try {
            // Delete entries and data
            $this->entriesManager->deleteEntries($ids, $deleterId);
        } catch (DatabaseException $e) {
            return $this->respond($e->getMessage(), statusCode: 500, success: false);
        }

        /* End of action */

        /* Response */

        $deletedEntries = $this->entriesManager->findDeletedEntries($ids);

        // Build a success response.
        $successMessage = (count($deletedEntries) > 1) ? lang('Admin.entriesSuccessfullyDeleted') : lang('Admin.entryxSuccessfullyDeleted', ['x' => $deletedEntries[0]['model_name']]);

        return $this->respond(
            $successMessage,
            'admin/entries'
        );

        /* End of response */
    }

    public function purgeDeleted()
    {

        /* Validation */

        $deleteableEntries = $this->entriesManager->countDeleted();

        if (!$deleteableEntries) {
            return $this->response->setStatusCode(400, lang('Admin.trashIsEmpty'));
        }

        /* End of validation */

        /* Action */

        try {
            $this->entriesManager->purgeDeleted();
        } catch (DatabaseException $e) {
            return $this->respond($e->getMessage(), statusCode: 500, success: false);
        }

        /* End of action */

        /* Response */

        return $this->respond(lang('Admin.entriesSuccessfullyDeleted'));

        /* End of response */
    }

    public function restore($id = null)
    {
        /* Validation */

        // Retrieve an array of IDs from POST data.
        $ids = $this->request->getPost('ids') ?: ($id ? [$id] : []);

        // If we still don't have any IDs, respond accordingly.
        if (empty($ids)) {
            return $this->respond(lang('Admin.noEntrySelected'), statusCode: 400, success: false);
        }

        // List all deleted entries
        $entries = $this->entriesManager->findDeletedEntries($ids);

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
            return $this->respond(lang('Admin.noRestorableEntriesFound'), statusCode: 400, success: false);
        }

        /* End of validation */

        /* Action */

        try {
            $this->entriesManager->restore($ids);
        } catch (DatabaseException $e) {
            return $this->respond($e->getMessage(), statusCode: 500, success: false);
        }

        /* End of action */

        /* Response */

        // Prepare a success message.
        $successMessage = (count($entries) > 1) ? lang('Admin.xentriesSuccessfullyRestored', ['x' => count($restorableEntryIds)]) : lang('Admin.entryxSuccessfullyRestored', ['x' => $entries[0]['model_name']]);

        // If unrestorable entries exist
        if (!empty($unrestorableEntryIds)) {
            $successMessage .= ' ' . lang('Admin.unableToRestorexEntries', ['x' => count($unrestorableEntryIds)]);
        }

        return $this->respond($successMessage);

        /* End of response */
    }

    /**
     * Process any uploaded files.
     *
     * Loops through the uploaded files, moves them to a designated folder, 
     * and collects the resulting URLs.
     *
     * @return array  [fileUrls, log]
     */
    private function processUploadedFiles(): array
    {
        $files    = $this->request->getFiles();
        $fileUrls = [];
        $log      = [];

        if ($files) {
            foreach ($files as $fileInputName => $uploadedFiles) {
                // Ensure it's an array (wrap single file in an array).
                if (!is_array($uploadedFiles)) {
                    $uploadedFiles = [$uploadedFiles];
                }

                foreach ($uploadedFiles as $file) {
                    if ($file instanceof UploadedFile) {
                        if ($file->isValid() && ! $file->hasMoved()) {
                            // Log file details (for debugging purposes).
                            $log['file_details'][] = [
                                'input_name'    => $fileInputName,
                                'original_name' => $file->getClientName(),
                                'temp_path'     => $file->getTempName(),
                                'file_size'     => $file->getSize(),
                                'file_type'     => $file->getMimeType(),
                            ];

                            // Use a slugified original name and a random name component.
                            $originalName = url_title(pathinfo($file->getClientName(), PATHINFO_FILENAME), '-', false);
                            $randomName   = $file->getRandomName();

                            // @TODO: Make the destination path configurable.
                            // Final destination path (adjust as necessary).
                            $destination = FCPATH . $this->uploadDestination;

                            // Move the file.
                            $file->move($destination, $originalName . '-' . $randomName);

                            // Record the relative URL.
                            $fileUrls[$fileInputName][] = $this->uploadDestination . $originalName . '-' . $randomName;
                        } else {
                            // If the file is invalid, log the error.
                            $log['file_errors'][] = [
                                'input_name'    => $fileInputName,
                                'error_message' => $file->getErrorString(),
                            ];
                        }
                    }
                }
            }
        }

        return [$fileUrls, $log];
    }

    /**
     * Update meta fields with file URLs.
     *
     * This method decodes the existing JSON meta field data, merges in file upload URLs,
     * and returns an updated JSON string.
     *
     * @param string|null $metaJson  The original JSON string.
     * @param array       $fileUrls  Uploaded file URLs keyed by input name.
     *
     * @return string  Updated JSON-encoded meta data.
     */
    private function updateMetaFields(?string $metaJson, array $fileUrls): string
    {
        // Decode the original meta data.
        $fieldsArray   = json_decode($metaJson, true) ?? [];
        $metaDataAssoc = [];

        // Create an associative array for easy lookup.
        foreach ($fieldsArray as $meta) {
            $metaDataAssoc[$meta['id']] = $meta['value'];
        }

        // Overwrite or add file URLs.
        foreach ($fileUrls as $fileInputName => $urls) {
            $metaDataAssoc[$fileInputName] = $urls;
        }

        // Convert back to a simple indexed array.
        $finalFields = [];
        foreach ($metaDataAssoc as $id => $value) {
            $finalFields[] = [
                'id'    => $id,
                'value' => $value,
            ];
        }

        // log_message('debug', "updateMetaFields: " . json_encode($finalFields, JSON_PRETTY_PRINT));

        return json_encode($finalFields);
    }
}
