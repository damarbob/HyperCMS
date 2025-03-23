<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Models\ModelsModel;
use CodeIgniter\HTTP\ResponseInterface;

class Models extends ApiController
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

        $model = new ModelsModel();
        $modelBuilder = $model->getCustomBuilder();

        // 1. Get the total count with no filtering.
        $totalRecords = $modelBuilder->countAllResults(false);

        // 2. Apply search filter if provided.
        if (!empty($search)) {
            $modelBuilder->like('name', $search);
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
