<?php

namespace App\Controllers\API\v1;

use App\Constants\ModelStaticFields;
use App\Controllers\API\v1\ApiController;
use StarDust\Models\EntriesModel;
use StarDust\Models\ModelsModel;

class Model extends ApiController
{
    public function index()
    {
        // Retrieve standard DataTables POST parameters
        $data = $this->request->getPost();

        return $this->response->setJSON($this->getModelData($data));
    }

    /**
     * Get model data with flexible input (either from POST or direct parameters)
     * 
     * @param array $params Array containing:
     *   - id: Model ID (required)
     *   - draw: Datatables draw counter
     *   - start: Pagination start
     *   - length: Number of records per page
     *   - search: Search value
     *   - order: Ordering information
     *   - columns: Columns information
     *   - find: Specific field/value to find
     * @return array Processed data for DataTables
     */
    public function getModelData(array $params): array
    {
        $modelId = $params['id'] ?? null;
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $search = $params['search']['value'] ?? ($params['search'] ?? '');
        $order = $params['order'] ?? null;
        $columns = $params['columns'] ?? null;
        $find = $params['find'] ?? null;
        $trash = $params['trash'] ?? false;

        // Validate model ID
        if (!$modelId) {
            throw new \InvalidArgumentException("No model id provided.");
        }

        // Get model and field types
        $modelInfo = $this->getModelInfo($modelId);
        $model = $modelInfo['model'];
        $dateFields = $modelInfo['dateFields'];
        $numericFields = $modelInfo['numericFields'];
        $codeFields = $modelInfo['codeFields'];

        // Build query
        $entriesModelBuilder = $this->buildBaseQuery($modelId, !$trash || $trash == 'false');

        // Apply necessary filters
        $this->applyFindFilter($entriesModelBuilder, $find);

        $totalRecords = $entriesModelBuilder->countAllResults(false); // Count total results after applying necessary filters

        // Apply optional filters
        $this->applySearchFilter($entriesModelBuilder, esc($search));

        $recordsFiltered = $entriesModelBuilder->countAllResults(false); // Count filtered results after optional filters

        // Apply ordering
        $this->applyOrdering($entriesModelBuilder, $order, $columns, $dateFields, $numericFields);

        // Apply pagination
        $this->applyPagination($entriesModelBuilder, $length, $start);

        // Get and process data
        $data = $this->processResultData($entriesModelBuilder->get()->getResultArray(), $codeFields);

        return [
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data,
        ];
    }

    /**
     * Get model information and field types
     */
    protected function getModelInfo(int $modelId): array
    {
        $modelsModel = model('modelsModel');
        $model = $modelsModel->stardust()->withLegacyAliases(true)->where('id', $modelId)->get()->getRow();

        if (!$model) {
            throw new \InvalidArgumentException("Model with id $modelId not found.");
        }

        $modelFields = json_decode($model->fields);
        $dateFields = [];
        $numericFields = [];
        $codeFields = [];

        foreach ($modelFields as $field) {
            if (!empty($field->type)) {
                if ($field->type == 'datetime-local') {
                    $dateFields[] = $field->id;
                } elseif ($field->type == 'number') {
                    $numericFields[] = $field->id;
                } elseif ($field->type == 'code') {
                    $codeFields[] = $field->id;
                }
            }
        }

        return [
            'model' => $model,
            'dateFields' => $dateFields,
            'numericFields' => $numericFields,
            'codeFields' => $codeFields
        ];
    }

    /**
     * Build the base query for entries
     */
    protected function buildBaseQuery(int $modelId, bool $trash)
    {
        /** @var \StarDust\Models\EntriesModel */
        $entriesModel = model('entriesModel');

        if ($trash) {
            $entriesModelBuilder = $entriesModel->stardust()->withLegacyAliases(true);
        } else {
            $entriesModelBuilder = $entriesModel->stardust(true)->withLegacyAliases(true);
        }

        $entriesModelBuilder->where('model_id', $modelId);

        return $entriesModelBuilder;
    }

    /**
     * Apply find filter if specified
     * @todo In the future, handle multiple find conditions
     * as the builder's likeFields supports multiple conditions
     */
    protected function applyFindFilter(&$builder, ?array $find): void
    {
        /** @var \StarDust\Models\EntriesModel */
        $entriesModel = model('entriesModel');
        if ($find && !empty($find['field']) && !empty($find['value'])) {
            $builder->likeFields([$find]);
        }
    }

    /**
     * Apply search filter if specified
     */
    protected function applySearchFilter(&$builder, string $search): void
    {
        if (!empty($search)) {
            $builder->where("LOWER(CAST(entry_data.fields AS CHAR)) LIKE '%" . strtolower($search) . "%'");
        }
    }

    /**
     * Apply ordering to the query
     */
    protected function applyOrdering(&$builder, ?array $order, ?array $columns, array $dateFields, array $numericFields): void
    {
        if (!empty($order)) {
            $orderColumnIndex = $order[0]['column'];
            $orderDir = $order[0]['dir'];
            $orderColumn = $columns[$orderColumnIndex]['data'];

            if (in_array($orderColumn, ModelStaticFields::FIELD_LIST)) {
                $builder->orderBy($orderColumn, $orderDir);
            } else {
                if (in_array($orderColumn, $dateFields)) {
                    $orderExpr = "STR_TO_DATE( JSON_UNQUOTE( JSON_EXTRACT( entry_data.fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(entry_data.fields, 'one', '" . $orderColumn . "', NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ), '%Y-%m-%d %H:%i:%s' )";
                    $builder->orderBy($orderExpr, $orderDir, false);
                } elseif (in_array($orderColumn, $numericFields)) {
                    $orderExpr = "CAST( JSON_UNQUOTE( JSON_EXTRACT( entry_data.fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(entry_data.fields, 'one', '" . $orderColumn . "', NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ) AS DECIMAL(10,2) )";
                    $builder->orderBy($orderExpr, $orderDir, false);
                } else {
                    $orderExpr = "LOWER(TRIM(REGEXP_REPLACE( CAST(JSON_EXTRACT(entry_data.fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(entry_data.fields, 'one', '" . $orderColumn . "', NULL, '$[*].id'), '[', -1), ']', 1), '].value' )) AS CHAR), '<[^>]+>', '' )))";
                    $builder->orderBy($orderExpr, $orderDir, false);
                }
            }
        } else {
            $builder->orderBy('date_modified', 'DESC');
        }
    }

    /**
     * Apply pagination to the query
     */
    protected function applyPagination(&$builder, int $length, int $start): void
    {
        if ($length != -1) {
            $builder->limit(intval($length), intval($start));
        }
    }

    /**
     * Process the result data - pivot JSON fields and escape HTML for code fields
     */
    protected function processResultData(array $data, array $codeFields): array
    {
        foreach ($data as &$row) {
            if (isset($row['fields'])) {
                $fieldsArray = json_decode($row['fields'], true);

                if (is_array($fieldsArray)) {
                    // Check if it's the legacy list of objects format (indexed array)
                    if (array_is_list($fieldsArray) && !empty($fieldsArray) && isset($fieldsArray[0]['id'])) {
                        foreach ($fieldsArray as $field) {
                            if (isset($field['id']) && isset($field['value'])) {
                                $row['' . $field['id']] = $field['value'];
                            }
                        }
                    } else {
                        // It represents a key-value pair object (associative array)
                        foreach ($fieldsArray as $key => $value) {
                            $row['' . $key] = $value;
                        }
                    }
                }
                unset($row['fields']);
            }
        }

        // HTML escaping for code fields
        foreach ($data as $i => $item) {
            foreach ($codeFields as $x) {
                if (isset($item[$x])) {
                    $data[$i][$x] = htmlspecialchars($item[$x]);
                }
            }
        }

        return $data;
    }
}
