<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Libraries\SortColumn;

class Entries extends ApiController
{
    public function index()
    {
        // Retrieve standard DataTables POST parameters
        $data = $this->request->getPost();

        $id = $data['id'] ?? null; // Entry id
        $ids = $data['ids'] ?? null; // Entry ids
        $modelId = $data['model_id'] ?? null; // Model id
        $draw = $data['draw'] ?? 1;;
        $start = $data['start'] ?? null; // Offset
        $length = $data['length'] ?? -1;   // Number of records per page
        $search = $data['search']['value'] ?? '';
        $order = $data['order'] ?? null;
        $columns = $data['columns'] ?? null;
        $trash = $data['trash'] ?? false;

        /** @var \StarDust\Models\EntriesModel */
        $model = model('entriesModel');

        // Apply trash filter
        if (!$trash || $trash == 'false') {
            $modelBuilder = $model->stardust()->withLegacyAliases(true);
        } else {
            $modelBuilder = $model->stardust(true)->withLegacyAliases(true);
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
        // DataTables provides the column index and sort direction, and the columns array
        // names the column. Only a plain column name may reach the query.
        $orderColumn = !empty($order) ? ($columns[$order[0]['column'] ?? 0]['data'] ?? null) : null;
        if (SortColumn::isSafe($orderColumn)) {
            $modelBuilder->orderBy($orderColumn, $order[0]['dir'] ?? '');
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
