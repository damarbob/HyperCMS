<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Models\ModelDataModel;
use App\Models\ModelsModel;

class ModelData extends ApiController
{
    public function index()
    {
        // Retrieve standard DataTables POST parameters
        $data = $this->request->getPost();

        $modelId = $data['id'] ?? null;
        $draw   = $data['draw'] ?? 1;
        $start  = $data['start'] ?? null;    // Offset
        $length = $data['length'] ?? -1;   // Number of records per page
        $search = $data['search']['value'] ?? '';
        $order  = $data['order'] ?? null;
        $columns = $data['columns'] ?? null;

        /** @var ModelDataModel */
        $modelData = model('ModelDataModel');
        $modelDataBuilder = $modelData->getCustomBuilder();

        // Get the total count with no filtering.
        $totalRecords = $modelDataBuilder->countAllResults(false);

        // 1. Filter by model_id if provided:
        if (!empty($modelId)) {
            $modelDataBuilder->where('id', $modelId);
        }

        // 2. Apply search filter if provided.
        if (!empty($search)) {
            $modelDataBuilder->groupStart();
            $modelDataBuilder->like('name', esc($search));
            $modelDataBuilder->orLike('fields', esc($search));
            $modelDataBuilder->orLike('created_by', esc($search));
            $modelDataBuilder->groupEnd();
        }

        // Count the filtered results.
        $recordsFiltered = $modelDataBuilder->countAllResults(false);

        // 3. Apply ordering, if provided.
        if (!empty($order)) {
            // DataTables provides the column index and sort direction.
            // We use the columns array to look up the actual column name.
            $orderColumnIndex = $order[0]['column'];
            $orderDir = $order[0]['dir'];
            $orderColumn = $columns[$orderColumnIndex]['data'];
            $modelDataBuilder->orderBy($orderColumn, $orderDir);
        } else {
            // Default ordering by date_modified DESC
            $modelDataBuilder->orderBy('date_modified', 'DESC');
        }

        // 4. Apply limit for pagination (if length is -1, that means no limit).
        if ($length != -1) {
            $modelDataBuilder->limit(intval($length), intval($start));
        }

        // 5. Fetch data from the database.
        $data = $modelDataBuilder->get()->getResultArray();

        // 6. Prepare and output the JSON response.
        $output = [
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $recordsFiltered,
            "data" => $data,
        ];

        return $this->response->setJSON($output);
    }
}
