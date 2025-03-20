<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Models\EntriesModel;
use App\Models\EntryDataModel;
use App\Models\ModelsModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;

class Model extends ApiController
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

        // Get the total count with no filtering.
        $totalRecords = $modelBuilder->countAllResults(false);

        // 1. Filter by model_id if provided:
        if (!empty($data['id'])) {
            $modelBuilder->where('model_id', $data['id']);
        }

        // Get UPDATED the total count with by id.
        $totalRecords = $modelBuilder->countAllResults(false);

        // 2. Apply search filter if provided.
        if (!empty($search)) {
            $modelBuilder->like('fields', $search);
        }

        // Count the filtered results.
        $recordsFiltered = $modelBuilder->countAllResults(false);

        // 3. Apply ordering, if provided.
        if (!empty($order)) {
            // DataTables provides the column index and sort direction.
            // We use the columns array to look up the actual column name.
            $orderColumnIndex = $order[0]['column'];
            $orderDir = $order[0]['dir'];
            $orderColumn = $columns[$orderColumnIndex]['data']; // Column name

            // Build a raw order expression that:
            // 1. Extracts the value of "konten" (at index [1]) from the fields column using JSON_EXTRACT,
            // 2. Casts it as CHAR,
            // 3. Uses REGEXP_REPLACE() to remove HTML tags (the pattern '<[^>]+>' matches HTML tags),
            // 4. Optionally applies LOWER() and TRIM() for consistent ordering.
            $orderExpr = "LOWER(TRIM(REGEXP_REPLACE(CAST(JSON_EXTRACT(fields, '$[$orderColumnIndex].value') AS CHAR), '<[^>]+>', '')))";
            $modelBuilder->orderBy($orderExpr, $orderDir, false);
        }

        // 4. Apply limit for pagination (if length is -1, that means no limit).
        if ($length != -1) {
            $modelBuilder->limit(intval($length), intval($start));
        }

        // 5. Fetch data from the database.
        $data = $modelBuilder->get()->getResultArray();

        // 6. Dynamically pivot the JSON field into separate columns.
        foreach ($data as &$row) {
            if (isset($row['fields'])) {
                // Decode the JSON into an associative array.
                $fieldsArray = json_decode($row['fields'], true);

                if (is_array($fieldsArray)) {
                    // For each field in the array, add a new key to the row.
                    foreach ($fieldsArray as $field) {
                        if (isset($field['id']) && isset($field['value'])) {
                            // Use a prefix (like "field_") to avoid collisions.
                            $row['' . $field['id']] = $field['value'];
                        }
                    }
                }
                // Optionally, remove the original JSON column
                unset($row['fields']);
            }
        }

        // 6. Prepare and output the JSON response.
        $output = [
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data,
        ];

        // return $this->response->setJSON($orderColumn);
        return $this->response->setJSON($output);

        ////////////////////////////////

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
}
