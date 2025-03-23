<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Editor extends BaseController
{
    public function index()
    {
        $data = $this->request->getGet();
        
        if (!$data || empty($data['entry_id'])) {
            return redirect()->back()->with('error', lang('Admin.noEntryFound'));
        }
        
        $entry_id = $data['entry_id'];
        // Load the page entry record
        $entry = $this->entriesModel->getCustomBuilder()->where('id', $entry_id)->get()->getRow();
        if (!$entry) {
            return redirect()->back()->with('error', lang('Admin.noEntryFoundWithIdx', ['x' => $entry_id]));
        }
        
        // Map entry fields for easier access, e.g., hyper_component_elements, hyper_css, etc.
        $mappedEntryFields = (object) array_column(json_decode($entry->fields), 'value', 'id');
        
        $this->data['entry'] = $entry;
        $this->data['mapped_entry_fields'] = $mappedEntryFields;

        // @TESTING: Get test components for editor plugin blocks
        $testComponents = $this->entriesModel->getCustomBuilder()->where('model_name', 'Component')->get()->getResultArray();
        foreach ($testComponents as &$row) {
            if (isset($row['fields'])) {
                $fieldsArray = json_decode($row['fields'], true);
                if (is_array($fieldsArray)) {
                    foreach ($fieldsArray as $field) {
                        if (isset($field['id']) && isset($field['value'])) {
                            $row[$field['id']] = $field['value'];
                        }
                    }
                }
                unset($row['fields']);
            }
        }
        $this->data['test_components'] = $testComponents;

        return view('admin/editor', $this->data);
    }
    
}
