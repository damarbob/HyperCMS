<?php

namespace App\Controllers\Admin;

use App\Constants\EntryDataStaticFields;
use App\Controllers\AdminController;

class EntryData extends AdminController
{

    public function show($entryId)
    {

        $id = $entryId;

        /* Entry */

        $entry = service('entryStore')->findAliased($id);

        // Check if the entry exists
        if (!$entry)
            return redirect('admin/entries')->with('error', lang('Admin.noEntryFoundWithIdx', ['x' => $id]));

        /* End of entry */

        /* Model */

        $found = service('modelStore')->find($entry['model_id']);
        $modelResult = $found ? [(array) $found] : [];

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
        $entriesHistory = service('entryStore')->versionsOf($id);

        foreach ($entriesHistory as &$row) {
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

        /* End of entry history */

        $this->data['entry'] = $entry;
        $this->data['fields'] = $entriesHistoryFields;
        $this->data['date_field_ids'] = json_encode($date_field_ids);
        $this->data['invisible_fields'] = $invisibleFields;

        $this->data['pageLenth'] = service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 10;

        $this->data['title'] = lang('Admin.entryxData', ['x' => $entryId]);

        return render('admin/entry_data', $this->data);
    }

    public function clearHistory($entryId = null)
    {
        // Check if the entry ID is provided.
        if (empty($entryId)) {
            return $this->respond(
                message: lang('Admin.noEntryFound'),
                statusCode: 400,
                success: false
            );
        }

        // Delete all history records for this entry but keep the newest one.
        $affected = service('entryStore')->clearHistory($entryId);

        // Check if a record was found.
        if ($affected === null) {
            return $this->respond(
                message: lang('Admin.noHistoryFound'),
                statusCode: 404,
                withInput: false, // The original code didn't use withInput()
                success: false    // This sets the key to 'error'
            );
        }

        $message = ($affected > 0)
            ? lang('Admin.historySuccessfullyCleared')
            : lang('Admin.noHistoryCleared');

        // Using respond(): Respond as JSON if the request expects JSON, otherwise perform a redirect.
        return $this->respond(
            message: $message,
            redirectTo: 'admin/entries',
            withInput: false
        );
    }
}
