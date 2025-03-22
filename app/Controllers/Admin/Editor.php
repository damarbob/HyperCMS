<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Editor extends BaseController
{
    public function index(): string
    {

        $testComponents = $this->entriesModel->get()->where('model_name', 'Component')->get()->getResultArray();

        // 6. Dynamically pivot the JSON field into separate columns.
        foreach ($testComponents as &$row) {
            if (isset($row['fields'])) {
                // Decode the JSON into an associative array.
                $fieldsArray = json_decode($row['fields'], true);

                if (is_array($fieldsArray)) {
                    // For each field in the array, add a new key to the row.
                    foreach ($fieldsArray as $field) {
                        if (isset($field['id']) && isset($field['value'])) {
                            // Use a prefix (like "field_") to avoid collisions.
                            $row['' . $field['id']] = $field['value'];
                        }
                    }
                }
                // Optionally, remove the original JSON column
                unset($row['fields']);
            }
        }

        // Use test components
        $this->data['test_components'] = $testComponents;
        
        // dd($this->data['test_components']);

        // Display the admin dashboard view
        return view('admin/editor', $this->data);
    }
}
