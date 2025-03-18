<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Models\EntriesModel;
use App\Models\EntryDataModel;
use App\Models\ModelsModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;

class Entries extends ApiController
{
    public function index()
    {
        // dd($this->request->getPost());
        // Retrieve standard DataTables POST parameters
        $data = $this->request->getPost();

        $draw   = $data['draw'];
        $start  = $data['start'];    // Offset]
        $length = $data['length'];   // Number of records per page
        $search = $data['search']['value'] ?? '';
        $order  = $data['order'];
        $columns = $data['columns'];

        $model = new EntriesModel();
        $modelBuilder = $model->get();

        // Filter by model_id if provided:
        if (!empty($data['model_id'])) {
            $modelBuilder->where('model_id', $data['model_id']);
        }

        // 1. Get the total count with no filtering.
        $totalRecords = $modelBuilder->countAllResults(false);

        // 2. Apply search filter if provided.
        if (!empty($search)) {
            $modelBuilder->like('model_name', $search);
            $modelBuilder->orLike('fields', $search);
            $modelBuilder->orLike('created_by', $search);
        }

        // Count the filtered results.
        $recordsFiltered = $modelBuilder->countAllResults(false);

        // 3. Apply ordering, if provided.
        if (!empty($order)) {
            // DataTables provides the column index and sort direction.
            // We use the columns array to look up the actual column name.
            $orderColumnIndex = $order[0]['column'];
            $orderDir = $order[0]['dir'];
            $orderColumn = $columns[$orderColumnIndex]['data'];
            $modelBuilder->orderBy($orderColumn, $orderDir);
        }

        // 4. Apply limit for pagination (if length is -1, that means no limit).
        if ($length != -1) {
            $modelBuilder->limit(intval($length), intval($start));
        }

        // 5. Fetch data from the database.
        $data = $modelBuilder->get()->getResultArray();

        // 6. Prepare and output the JSON response.
        $output = [
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data,
        ];

        return $this->response->setJSON($output);
        // return $this->response->setJSON($data('draw'));
    }

    /**
     * Create a new entry.
     */
    public function create($model_id)
    {
        // $model_id = 10000;
        // Default failure response.
        $response = ['success' => false, 'message' => lang('Admin.terjadiGalat')];

        // Dependency: instantiate the model that handles your entry data.
        $modelsModel = new ModelsModel();
        $entriesModel = new EntriesModel();
        $entryDataModel = new EntryDataModel();

        // Retrieve meta fields from the request.
        $metaJson = $this->request->getPost('fields');

        // Get the model
        $model = $modelsModel->get()->where('id', $model_id)->limit(1)->get()->getResultArray();

        // return $this->response->setJSON(['success' => false, 'message' => json_encode($model)]);

        // Validate the model ID.
        if (!$model) {
            $response = ['success' => false, 'message' => lang('Admin.noModelFoundWithIdx', ['x' => $model_id])];
            return $this->response->setJSON($response);
        }

        $entry = [
            'model_id' => $model_id,
            'creator_id' => auth()->user()->id,
        ];

        // Process any file uploads (returns file URLs & a log for debugging if needed).
        list($fileUrls, $fileLog) = $this->processUploadedFiles();

        // Attempt entry insertion.
        if (!$entriesModel->insert($entry)) {
            $response = ['success' => false, 'message' => lang('Admin.failedToSave')];
        }

        // Update the meta data with any file URLs from the upload.
        $encodedFields = $this->updateMetaFields($metaJson, $fileUrls);

        // Build the data array for insertion.
        $data = [
            'entry_id' => $entriesModel->getInsertID(),
            'fields'     => $encodedFields,
            'creator_id' => auth()->user()->id,
        ];

        // Attempt entry data insertion.
        if ($entryDataModel->insert($data)) {
            $response = ['success' => true, 'message' => lang('Admin.successfullySaved')];
        } else {
            $response = ['success' => false, 'message' => lang('Admin.failedToSave')];
        }

        return $this->response->setJSON($response);
    }

    /**
     * Save an entry with an existing entry_id.
     *
     * @param int|string $entry_id
     * @return \CodeIgniter\HTTP\Response
     */
    public function save($entry_id)
    {
        $response = ['success' => false, 'message' => lang('Admin.terjadiGalat')];

        // Instantiate the EntryDataModel.
        $entryDataModel = new EntryDataModel();

        // Retrieve the meta fields passed via POST.
        $metaJson = $this->request->getPost('fields');

        // Process file uploads. This returns an array of file URLs and a log array.
        list($fileUrls, $fileLog) = $this->processUploadedFiles();

        // Update meta fields with any file URLs.
        $encodedFields = $this->updateMetaFields($metaJson, $fileUrls);

        // Build the data array including the entry ID.
        $data = [
            'entry_id'   => $entry_id,
            'fields'     => $encodedFields,
            'creator_id' => auth()->user()->id,
        ];

        // Insert the record.
        if ($entryDataModel->insert($data)) {
            $response = ['success' => true, 'message' => lang('Admin.successfullySaved')];
        } else {
            $response = ['success' => false, 'message' => lang('Admin.failedToSave')];
        }

        return $this->response->setJSON($response);
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
                if (! is_array($uploadedFiles)) {
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

                            // Final destination path (adjust as necessary).
                            $destination = FCPATH . 'tests/uploads';

                            // Move the file.
                            $file->move($destination, $originalName . '-' . $randomName);

                            // Record the relative URL.
                            $fileUrls[$fileInputName][] = "tests/uploads/" . $originalName . '-' . $randomName;
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

        return json_encode($finalFields);
    }
}
