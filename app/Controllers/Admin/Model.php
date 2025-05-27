<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Constants\ModelStaticFields;

class Model extends AdminController
{

    /**
     * Display the details for a given model.
     *
     * This method retrieves a model record by its ID, decodes its JSON-defined fields,
     * and then augments the list with mandatory fields (e.g. "edited_by" and "date_modified").
     * It also prepares additional metadata (e.g. indices of datetime fields for specialized
     * processing in the view) and finally renders the view.
     *
     * @param mixed $id The model ID to display. Must be non-empty.
     * 
     * @return \CodeIgniter\HTTP\Response Redirect or view response.
     */
    public function index($id)
    {
        // Ensure an ID is provided; if not, redirect back to entries.
        if (empty($id)) {
            return redirect('admin/entries');
        }

        // Retrieve model record(s) using a custom query builder.
        $models = $this->modelsModel->getCustomBuilder()
            ->where('id', $id)
            ->get()
            ->getResult();

        // If no model is found, redirect with an error message.
        if (!$models) {
            return redirect('admin/entries')
                ->with('error', lang('Admin.noModelFoundWithIdx', ['x' => $id]));
        }

        // Use the first (and expected only) model record.
        $model = $models[0];

        // Define invisible fields that are always present.
        $invisibleFields = [
            (object)[
                'title' => lang('Admin.id'),
                'id'    => 'id',
            ],
            (object)[
                'title' => lang('Admin.model'),
                'id'    => 'model_name',
            ],
        ];

        // Initialize $fields array.
        $fields = [];

        // Decode the JSON-defined fields from the model record.
        // Use an empty array if the JSON is empty or invalid.
        $modelFields = json_decode($model->fields);
        if (!empty($modelFields)) {
            foreach ($modelFields as $field) {
                $fields[] = $field;
            }
        }

        // Append mandatory fields.
        $fields[] = (object)[
            'id'    => ModelStaticFields::EDITED_BY,
            'label' => lang('Admin.editedBy'),
            'type'  => 'text',
        ];
        $fields[] = (object)[
            'id'    => ModelStaticFields::DATE_MODIFIED,
            'label' => lang('Admin.dateModified'),
            'type'  => 'datetime-local',
        ];

        /*
     * Build a list of indices for fields that are of type 'datetime-local'.
     * The indices are offset by +2 to account for the two invisible fields (id and model_name),
     * which are assumed to come first when rendering.
     */
        $dateFieldIds = [];
        foreach ($fields as $index => $field) {
            if (isset($field->type) && $field->type === 'datetime-local') {
                $dateFieldIds[] = $index + 2;
            }
        }

        // Prepare the data to be passed to the view.
        $this->data = array_merge($this->data, [
            'date_field_ids' => json_encode($dateFieldIds),
            'fields' => $fields,
            'invisible_fields' => $invisibleFields,
            'id' => $id,
            'pageLength' => service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 10,
            'title' => $model->name,
            'links' => [
                'new' => base_url("admin/model/$id/new?model_id=$id"),
                'edit' => base_url("admin/model/$id/") . '{id}/edit', // The ID must be separated from the base URL to prevent it from being URL-encoded.
                'delete' => base_url('admin/entries/delete'),
                'restore' => base_url('admin/entries/restore'),
            ]
        ]);

        // Filter
        $this->data = $this->hooks->filter(hook('Backend.controller:model:index:data'), $this->data);

        // Return the view for the model details page.
        return render('admin/model', $this->data);
    }
}
