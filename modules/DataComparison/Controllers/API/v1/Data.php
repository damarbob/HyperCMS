<?php

namespace DataComparison\Controllers\API\v1;

use App\Controllers\AdminController;

class Data extends AdminController
{
    public function index()
    {
        // Retrieve standard DataTables POST parameters
        $data = $this->request->getVar();

        $id = $data['id'] ?? null; // Data id
        $entryId = $data['entry_id'] ?? null; // Entry id
        $entryIds = $data['entry_ids'] ?? null; // Entry ids
        $modelId = $data['model_id'] ?? null; // Model id

        /** @var \App\Models\EntriesModel */
        $model = model('entriesModel');

        $modelBuilder = $model->getCustomBuilder();

        // Filter by entry id if provided:
        if (!empty($entryId)) {
            $modelBuilder->where('id', $entryId);
        }

        // Filter by entry ids if provided:
        if (!empty($entryIds)) {
            $modelBuilder->whereIn('id', $entryIds);
        }

        // Filter by model_id if provided:
        if (!empty($modelId)) {
            $modelBuilder->where('model_id', $modelId);
        }

        // Default ordering by date_modified DESC
        $modelBuilder->orderBy('date_modified', 'DESC');

        // Fetch data from the database.
        $data = $modelBuilder->get()->getResultArray();

        // Prepare and output the JSON response.
        $output = [
            "data" => $this->sendFieldsOnlyResponse($data),
        ];

        return $this->response->setJSON($output);
    }

    /**
     * Given an array of DB rows, each containing a 'fields' JSON string,
     * return a JSON string whose top-level key is "data" and whose value
     * is an array of field-only maps.
     *
     * @param array $rows  The original rows, each of which has ['fields'] = JSON array of {id,value}.
     * @return array       The resulting fields array.
     */
    protected function sendFieldsOnlyResponse(array $rows)
    {
        return array_map(function (array $row) {
            // Decode the JSON string into an array of ['id'=>…,'value'=>…]
            $flat = json_decode($row['fields'] ?? '[]', true) ?: [];

            // Turn it into [ fieldId => value, … ]
            $fields = array_column($flat, 'value', 'id');

            return [
                'id'     => $row['id'],
                'fields' => $fields,
            ];
        }, $rows);
    }
}
