<?php

namespace App\Controllers\Admin;

use App\Constants\EntryDataStaticFields;
use App\Controllers\BaseController;

class EntryData extends BaseController
{

    public function show($entryId)
    {

        $id = $entryId;

        /* Entry */

        $entriesResult = $this->entriesModel->getCustomBuilder()->where('id', $id)->limit(1)->get()->getResultArray();

        // Check if the entry exists
        if (!$entriesResult)
            return redirect('admin/entries')->with('error', lang('Admin.noEntryFoundWithIdx', ['x' => $id]));

        $entry = $entriesResult[0];

        /* End of entry */

        /* Model */

        $modelResult = $this->modelsModel->getCustomBuilder()->where('id', $entry['model_id'])->limit(1)->get()->getResultArray();

        // Check if the model exists
        if (empty($modelResult))
            return redirect('admin/entries')->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $modelResult['name']]));

        $model = $modelResult[0]; // Assign the model

        /* End of model */

        /* Configs */

        $invisibleFields = [
            (object) [
                'title' => lang('Admin.id'),
                'id' => 'id',
            ],
            (object) [
                'title' => lang('Admin.model'),
                'id' => 'model_name',
            ]
        ];

        // Entry history columns
        $entriesHistoryFields = [];

        // Check if the model fields are not empty
        // Then push them to the fields array
        // To prevent errors when decoding JSON
        $modelFields = json_decode($model['fields']);
        if (!empty($modelFields)) {
            foreach ($modelFields as $field) {
                array_push($entriesHistoryFields, $field);
            }
        }

        // Put mandatory fields (created_at and date_created)
        array_push($entriesHistoryFields, (object) [
            'id' => EntryDataStaticFields::CREATED_BY,
            'label' => lang('Admin.createdBy'),
            'type' => 'text',
        ]);
        array_push($entriesHistoryFields, (object) [
            'id' => EntryDataStaticFields::DATE_CREATED,
            'label' => lang('Admin.dateCreated'),
            'type' => 'datetime-local',
        ]);

        // List fields with date type
        $date_field_ids = [];
        foreach ($entriesHistoryFields as $i => $field) {
            if ($field->type == 'datetime-local') {
                $date_field_ids[] = $i + 2; // @IMPORTANT: +2 because of the invisible id and model_name fields
            }
        }

        /* End of configs */

        /* Entry history */

        // Entry history
        $entriesHistory = $this->entryDataModel->getCustomBuilder()
            ->where('entry_id', $id)
            ->offset(1)  // Skip the first record.
            ->get()
            ->getResultArray();

        foreach ($entriesHistory as &$row) {
            if (isset($row['fields'])) {
                $fieldsArray = json_decode($row['fields'], true);

                if (is_array($fieldsArray)) {
                    foreach ($fieldsArray as $field) {
                        if (isset($field['id']) && isset($field['value'])) {
                            $row['' . $field['id']] = $field['value'];
                        }
                    }
                }
                unset($row['fields']);
            }
        }

        /* End of entry history */

        $this->data['entry'] = $entry;
        $this->data['fields'] = $entriesHistoryFields;
        $this->data['date_field_ids'] = json_encode($date_field_ids);
        $this->data['invisible_fields'] = $invisibleFields;

        $this->data['title'] = lang('Admin.entryxData', ['x' => $entryId]);

        return view('admin/entry_data', $this->data);
    }

    public function clearHistory($entryId = null)
    {
        // Check if the entry ID is provided.
        if (empty($entryId)) {
            $errorResponse = ['error' => lang('Admin.noEntryFound')];
            if ($this->request->isAJAX() || strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false) {
                return $this->response->setStatusCode(400)->setJSON($errorResponse);
            }
            return redirect()->back()->with('error', lang('Admin.noEntryFound'));
        }

        // Retrieve the newest record for this entry.
        $newestRecord = $this->entryDataModel
            ->select('id')
            ->where('entry_id', $entryId)
            ->orderBy('id', 'DESC') // Or order by 'created_at' if available.
            ->limit(1)
            ->get()
            ->getRow();

        if (empty($newestRecord)) {
            $errorMessage = lang('Admin.noHistoryFound');
            if ($this->request->isAJAX() || strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false) {
                return $this->response->setStatusCode(404)->setJSON(['error' => $errorMessage]);
            }
            return redirect()->back()->with('error', $errorMessage);
        }

        // Delete all history records for this entry but exclude the newest one.
        $this->entryDataModel
            ->where('entry_id', $entryId)
            ->where('id !=', $newestRecord->id)
            ->delete(purge: true);

        // Optionally, check if any rows were affected.
        $affected = $this->entryDataModel->db->affectedRows();
        $message = ($affected > 0)
            ? lang('Admin.historySuccessfullyCleared')
            : lang('Admin.noHistoryCleared');

        // Respond as JSON if the request expects JSON, otherwise perform a redirect.
        if ($this->request->isAJAX() || strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false) {
            return $this->response->setStatusCode(200)->setJSON(['success' => $message]);
        }

        return redirect('admin/entries')->with('success', $message);
    }
}
