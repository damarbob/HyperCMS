<?php

namespace Modules\PagingSystem\Controllers\Admin;

use App\Controllers\BaseController;

class Editor extends BaseController
{
    public function index()
    {
        $data = $this->request->getGet(); // Get data from get

        // Return if entry_id is empty
        if (!$data || empty($data['entry_id'])) {
            return redirect()->back()->with('error', lang('Admin.noEntryFound'));
        }
        $entryId = $data['entry_id']; // Assign entry id

        // Load the page entry record
        $entry = (object) $this->entriesManager->find($entryId);

        if (!$entry) {
            return redirect()->back()->with('error', lang('Admin.noEntryFoundWithIdx', ['x' => $entryId]));
        }

        // Map entry fields for easier access, e.g., hyper_component_elements, hyper_css, etc.
        $mappedEntryFields = array_column(json_decode($entry->fields), 'value', 'id');

        $this->loadUserCreatedComponents();

        /* View data */

        $this->data['entry'] = $entry;
        $this->data['mapped_entry_fields'] = $mappedEntryFields;

        $this->data['scripts'] = []; // Script assets
        $this->data['styles'] = []; // Style assets

        $this->data['title'] = lang('PagingSystem.editor-x', ['x' => (isset($mappedEntryFields['hyper_title']) ? $mappedEntryFields['hyper_title'] : 'Untitled')]);

        /* Filters */

        $this->data = $this->hooks->filter(hook('PagingSystemBackend.controller:editor:index:data'), $this->data);

        return view('\Modules\PagingSystem\Views\Admin\editor', $this->data);
    }

    /**
     * Load user created components
     * @todo Finish user-created components 
     */
    private function loadUserCreatedComponents()
    {
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
    }
}
