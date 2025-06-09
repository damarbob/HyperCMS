<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;

class Entries extends ApiController
{
    public function index()
    {
        // Retrieve standard DataTables POST parameters
        $data = $this->request->getPost();

        $id = $data['id'] ?? null; // Entry id
        $ids = $data['ids'] ?? null; // Entry ids
        $modelId = $data['model_id'] ?? null; // Model id
        $draw   = $data['draw'] ?? 1;;
        $start  = $data['start'] ?? null; // Offset
        $length = $data['length'] ?? -1;   // Number of records per page
        $search = $data['search']['value'] ?? '';
        $order  = $data['order'] ?? null;
        $columns = $data['columns'] ?? null;
        $trash = $data['trash'] ?? false;

        /** @var \App\Models\EntriesModel */
        $model = model('EntriesModel');

        // Apply trash filter
        if (!$trash || $trash == 'false') {
            $modelBuilder = $model->getCustomBuilder();
        } else {
            $modelBuilder = $model->getDeletedCustomBuilder();
        }

        // Filter by entry id if provided:
        if (!empty($id)) {
            $modelBuilder->where('id', $id);
        }

        // Filter by entry ids if provided:
        if (!empty($ids)) {
            $modelBuilder->whereIn('id', $ids);
        }

        // Filter by model_id if provided:
        if (!empty($modelId)) {
            $modelBuilder->where('model_id', $modelId);
        }

        // 1. Get the total count with no filtering.
        $totalRecords = $modelBuilder->countAllResults(false);

        // 2. Apply search filter if provided.
        if (!empty($search)) {
            $modelBuilder->like('model_name', esc($search));
            $modelBuilder->orLike('fields', esc($search));
            $modelBuilder->orLike('created_by', esc($search));
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
        } else {
            // Default ordering by date_modified DESC
            $modelBuilder->orderBy('date_modified', 'DESC');
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
    }
}
