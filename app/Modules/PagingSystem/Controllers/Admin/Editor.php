<?php

namespace Modules\PagingSystem\Controllers\Admin;

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
        $entry = (object) $this->entriesManager->find($entry_id);

        if (!$entry) {
            return redirect()->back()->with('error', lang('Admin.noEntryFoundWithIdx', ['x' => $entry_id]));
        }

        // Map entry fields for easier access, e.g., hyper_component_elements, hyper_css, etc.
        $mappedEntryFields = array_column(json_decode($entry->fields), 'value', 'id');

        $this->data['entry'] = $entry;
        $this->data['mapped_entry_fields'] = $mappedEntryFields;

        /** @todo Finish user-created components */
        // Get test user-created components for editor plugin blocks
        if (ENVIRONMENT !== 'production') {
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
        }

        $this->data['title'] = lang('PagingSystem.editor-x', ['x' => (isset($mappedEntryFields['hyper_title']) ? $mappedEntryFields['hyper_title'] : 'Untitled')]);

        return view('\Modules\PagingSystem\Views\Admin\editor', $this->data);
    }
}
