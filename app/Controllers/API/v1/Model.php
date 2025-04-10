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
        // Retrieve standard DataTables POST parameters
        $data = $this->request->getPost();

        $modelId = $data['id'] ?? null;
        $draw   = $data['draw'] ?? 1;
        $start  = $data['start'] ?? 0; // Offset
        $length = $data['length'] ?? 10; // Number of records per page
        $search = $data['search']['value'] ?? '';
        $order  = $data['order'] ?? null;
        $columns = $data['columns'] ?? null;

        // Additional features
        $find = $data['find'] ?? null; // To find the most relevant item

        if (!$modelId) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => "No model id provided."]);
        }

        // Get the model
        $modelsModel = new ModelsModel();
        $model = $modelsModel->getCustomBuilder()->where('id', $modelId)->get()->getRow();

        if (!$model) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => "Model with id $modelId not found."]);
        }

        $modelFields = json_decode($model->fields);
        $dateFields = [];
        $numericFields = [];
        $codeFields = [];

        foreach ($modelFields as $field) {
            if ($field->type == 'datetime-local') {
                $dateFields[] = $field->id;
            } elseif ($field->type == 'number') {
                $numericFields[] = $field->id;
            } elseif ($field->type == 'code') {
                $codeFields[] = $field->id;
            }
        }
        // log_message('debug', 'code fields: ' . json_encode($codeFields));
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

        // 2. Find individual item
        if ($find && !empty($find['field']) && !empty($find['value'])) {
            $entriesModelBuilder->where("
                LOWER(
                    JSON_UNQUOTE(
                        JSON_EXTRACT(
                            fields,
                            CONCAT(
                                '$[',
                                SUBSTRING_INDEX(
                                    SUBSTRING_INDEX(
                                        JSON_SEARCH(fields, 'one', '" . $find['field'] . "', NULL, '$[*].id'),
                                        '[',
                                        -1
                                    ),
                                    ']',
                                    1
                                ),
                                '].value'
                            )
                        )
                    )
                ) LIKE '%" . strtolower($find['value']) . "%'
            ");
        }

        // Get UPDATED the total count with by id.
        $totalRecords = $entriesModelBuilder->countAllResults(false);

        // 3. Apply search filter if provided.
        if (!empty($search)) {
            $entriesModelBuilder->where("LOWER(CAST(fields AS CHAR)) LIKE '%" . strtolower($search) . "%'");
        }

        // Count the filtered results.
        $recordsFiltered = $entriesModelBuilder->countAllResults(false);

        // 4. Apply ordering, if provided.
        if (!empty($order)) {
            $orderColumnIndex = $order[0]['column'];
            $orderDir         = $order[0]['dir'];
            $orderColumn      = $columns[$orderColumnIndex]['data']; // This is the field key for dynamic fields

            if (in_array($orderColumn, ModelStaticFields::FIELD_LIST)) {
                $entriesModelBuilder->orderBy($orderColumn, $orderDir);
            } else {
                if (in_array($orderColumn, $dateFields)) {
                    // Build dynamic ordering expression for date fields:
                    $orderExpr = "STR_TO_DATE( JSON_UNQUOTE( JSON_EXTRACT( fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(fields, 'one', '" . $orderColumn . "', NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ), '%Y-%m-%d %H:%i:%s' )";
                    $entriesModelBuilder->orderBy($orderExpr, $orderDir, false);
                } elseif (in_array($orderColumn, $numericFields)) {
                    // For numeric fields, cast as decimal.
                    $orderExpr = "CAST( JSON_UNQUOTE( JSON_EXTRACT( fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(fields, 'one', '" . $orderColumn . "', NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ) AS DECIMAL(10,2) )";
                    $entriesModelBuilder->orderBy($orderExpr, $orderDir, false);
                } else {
                    // For regular text, remove HTML tags as before.
                    $orderExpr = "LOWER(TRIM(REGEXP_REPLACE( CAST(JSON_EXTRACT(fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(fields, 'one', '" . $orderColumn . "', NULL, '$[*].id'), '[', -1), ']', 1), '].value' )) AS CHAR), '<[^>]+>', '' )))";
                    $entriesModelBuilder->orderBy($orderExpr, $orderDir, false);
                }
            }
        } else {
            // Default ordering by date_modified DESC
            $entriesModelBuilder->orderBy('date_modified', 'DESC');
        }

        // 5. Apply limit for pagination (if length is -1, that means no limit).
        if ($length != -1) {
            $entriesModelBuilder->limit(intval($length), intval($start));
        }

        // 6. Fetch data from the database.
        $data = $entriesModelBuilder->get()->getResultArray();

        // 7. Dynamically pivot the JSON field into separate columns.
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
        }

        // 8. Prepare and output the JSON response.
        $output = [
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data,
        ];

        return $this->response->setJSON($output);
    }
}
