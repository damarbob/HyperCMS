<?php

namespace App\Controllers\API\v1;

use App\Constants\ModelStaticFields;
use App\Controllers\API\v1\ApiController;
use App\Models\EntriesModel;
use App\Models\ModelsModel;

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

        $modelsModel = new ModelsModel();
        $model = $modelsModel->getCustomBuilder()->where('id', $data['id'])->get()->getRow();

        // @WARNING: Assuming model is found
        // @IMPORTANT: Change the 'content' key if fields structure changed
        $modelFields = json_decode($model->fields);
        $dateFields = [];
        $numericFields = [];
        $codeFields = [];

        foreach ($modelFields as $field) {
            // log_message('debug', 'field: ' . json_encode($field->content));
            if ($field->content->tipe == 'datetime-local') {
                $dateFields[] = $field->content->id;
            } elseif ($field->content->tipe == 'number') {
                $numericFields[] = $field->content->id;
            } elseif ($field->content->tipe == 'code') {
                $codeFields[] = $field->content->id;
            }
        }
        log_message('debug', 'code fields: ' . json_encode($codeFields));
        // log_message('debug', 'date fields: ' . json_encode($dateFields));
        // log_message('debug', 'numeric fields: ' . json_encode($numericFields));

        $entriesModelBuilder = new EntriesModel();
        $entriesModelBuilder = $entriesModelBuilder->getCustomBuilder();

        // Get the total count with no filtering.
        $totalRecords = $entriesModelBuilder->countAllResults(false);

        // 1. Filter by model_id if provided:
        if (!empty($data['id'])) {
            $entriesModelBuilder->where('model_id', $data['id']);
        }

        // Get UPDATED the total count with by id.
        $totalRecords = $entriesModelBuilder->countAllResults(false);

        // 2. Apply search filter if provided.
        if (!empty($search)) {
            $entriesModelBuilder->like('fields', $search);
        }

        // Count the filtered results.
        $recordsFiltered = $entriesModelBuilder->countAllResults(false);

        // 3. Apply ordering, if provided.
        if (!empty($order)) {
            $orderColumnIndex = $order[0]['column'];
            $orderDir         = $order[0]['dir'];
            $orderColumn      = $columns[$orderColumnIndex]['data']; // This is the field key for dynamic fields

            if (in_array($orderColumn, ModelStaticFields::FIELD_LIST)) {
                $entriesModelBuilder->orderBy($orderColumn, $orderDir);
            } else {
                if (in_array($orderColumn, $dateFields)) {
                    // log_message('debug', 'order column (date) is: ' . $orderColumn);
                    // Build dynamic ordering expression for date fields:
                    $orderExpr = "STR_TO_DATE( JSON_UNQUOTE( JSON_EXTRACT( fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(fields, 'one', '" . $orderColumn . "', NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ), '%Y-%m-%d %H:%i:%s' )";
                    $entriesModelBuilder->orderBy($orderExpr, $orderDir, false);
                } elseif (in_array($orderColumn, $numericFields)) {
                    // log_message('debug', 'order column (numeric) is: ' . $orderColumn);
                    // For numeric fields, cast as decimal.
                    $orderExpr = "CAST( JSON_UNQUOTE( JSON_EXTRACT( fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(fields, 'one', '" . $orderColumn . "', NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ) AS DECIMAL(10,2) )";
                    $entriesModelBuilder->orderBy($orderExpr, $orderDir, false);
                } else {
                    // log_message('debug', 'order column (string) is: ' . $orderColumn);
                    // For regular text, remove HTML tags as before.
                    $orderExpr = "LOWER(TRIM(REGEXP_REPLACE( CAST(JSON_EXTRACT(fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(fields, 'one', '" . $orderColumn . "', NULL, '$[*].id'), '[', -1), ']', 1), '].value' )) AS CHAR), '<[^>]+>', '' )))";
                    $entriesModelBuilder->orderBy($orderExpr, $orderDir, false);
                }
            }
        }


        // 4. Apply limit for pagination (if length is -1, that means no limit).
        if ($length != -1) {
            $entriesModelBuilder->limit(intval($length), intval($start));
        }

        // 5. Fetch data from the database.
        $data = $entriesModelBuilder->get()->getResultArray();

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

        // HTML escaping for fields with type code
        foreach ($data as $i => $item) {
            // Iterate through code fields
            foreach ($codeFields as $x) {
                // If the field exists, escape the html
                if (isset($item[$x])) {
                    $data[$i][$x] = htmlspecialchars($item[$x]);
                }
            }
            // log_message('debug', json_encode($item));
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
        $totalRecords = $entriesModelBuilder->countAllResults(false);

        // 2. Apply search filter if provided.
        if (!empty($search)) {
            $entriesModelBuilder->like('model_name', $search);
            $entriesModelBuilder->orLike('fields', $search);
            $entriesModelBuilder->orLike('created_by', $search);
        }

        // Count the filtered results.
        $recordsFiltered = $entriesModelBuilder->countAllResults(false);

        // 3. Apply ordering, if provided.
        if (!empty($order)) {
            // DataTables provides the column index and sort direction.
            // We use the columns array to look up the actual column name.
            $orderColumnIndex = $order[0]['column'];
            $orderDir = $order[0]['dir'];
            $orderColumn = $columns[$orderColumnIndex]['data'];
            $entriesModelBuilder->orderBy($orderColumn, $orderDir);
        }

        // 4. Apply limit for pagination (if length is -1, that means no limit).
        if ($length != -1) {
            $entriesModelBuilder->limit(intval($length), intval($start));
        }

        // 5. Fetch data from the database.
        $data = $entriesModelBuilder->get()->getResultArray();

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
