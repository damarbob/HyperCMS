<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Models\EntriesModel;
use App\Models\EntryDataModel;
use App\Models\ModelsModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class Entries extends ApiController
{
    public function index()
    {
        // Retrieve standard DataTables POST parameters
        $data = $this->request->getPost();

        $draw   = $data['draw'] ?? 1;;
        $start  = $data['start'] ?? null;    // Offset]
        $length = $data['length'] ?? -1;   // Number of records per page
        $search = $data['search']['value'] ?? '';
        $order  = $data['order'] ?? null;
        $columns = $data['columns'] ?? null;
        $trash = $data['trash'] ?? false;

        /** @var \App\Models\EntriesModel */
        $model = model('entriesModel');

        // Apply trash filter
        if (!$trash || $trash == 'false') {
            $modelBuilder = $model->getCustomBuilder();
        } else {
            $modelBuilder = $model->getDeletedCustomBuilder();
        }

        // Filter by model_id if provided:
        if (!empty($data['model_id'])) {
            $modelBuilder->where('model_id', $data['model_id']);
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
        // return $this->response->setJSON($data('draw'));
    }
}
