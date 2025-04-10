<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Constants\ModelStaticFields;

class Model extends BaseController
{

    public function index()
    {
        $id = $this->request->getGet('id');

        if (!$id) {
            return redirect('admin/entries');
        }

        $models = $this->modelsModel->getCustomBuilder()
            ->where('id', $id)
            ->get()
            ->getResult();

        if (!$models) {
            return redirect('admin/entries')->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $id]));
        }

        $model = $models[0];

        $fields = [];
        $invisibleFields = [
            (object) [
                'title' => lang('Admin.id'),
                'id' => 'id',
            ],
            (object) [
                'title' => lang('Admin.model'),
                'id' => 'model_name',
            ],
        ];

        foreach (json_decode($model->fields) as $field) {
            array_push($fields, $field);
        }

        // Put mandatory fields (edited_by and date_modified
        array_push($fields, (object) [
            'id' => ModelStaticFields::EDITED_BY,
            'label' => lang('Admin.editedBy'),
            'type' => 'text',
        ]);
        array_push($fields, (object) [
            'id' => ModelStaticFields::DATE_MODIFIED,
            'label' => lang('Admin.dateModified'),
            'type' => 'datetime-local',
        ]);

        // List fields with date type
        $date_field_ids = [];
        foreach ($fields as $i => $field) {
            if ($field->type == 'datetime-local') {
                $date_field_ids[] = $i + 2; // @IMPORTANT: +2 because of the invisible id and model_name fields
            }
        }

        $this->data['date_field_ids'] = json_encode($date_field_ids);
        $this->data['fields'] = $fields;
        $this->data['invisible_fields'] = $invisibleFields;

        $this->data['title'] = $model->name; // Set the title if model is not empty

        $this->data['id'] = $id;

        // Display the admin dashboard view
        return view('admin/model', $this->data);
    }
}
