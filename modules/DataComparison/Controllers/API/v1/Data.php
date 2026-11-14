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

        // Newest first, narrowed by whichever of entry id, entry ids and model id were sent.
        $data = service('entryStore')->recent([
            'id' => $entryId,
            'ids' => $entryIds,
            'model_id' => $modelId,
        ]);

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
            // Decode the JSON string
            $flat = json_decode($row['fields'] ?? '[]', true) ?: [];

            // Handle both old format [{'id': x, 'value': y}] and new format {x: y}
            if (is_array($flat) && array_is_list($flat) && !empty($flat) && isset($flat[0]['id'])) {
                $fields = array_column($flat, 'value', 'id');
            } else {
                $fields = $flat;
            }

            return [
                'id' => $row['id'],
                'fields' => $fields,
            ];
        }, $rows);
    }
}
