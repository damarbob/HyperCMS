<?php

namespace PagingSystem\Controllers\Admin;

use App\Controllers\AdminController;
use CodeIgniter\Exceptions\PageNotFoundException;

class Editor extends AdminController
{
    protected $helpers = ['hyper_url'];

    public function index()
    {
        $data = $this->request->getGet(); // Get data from get

        // Return if entry_id is empty
        if (!$data || empty($data['entry_id'])) {
            throw PageNotFoundException::forPageNotFound(
                lang('Admin.noEntryFound')
            );
        }
        $entryId = $data['entry_id']; // Assign entry id

        // Load the page entry record
        $entry = $this->entriesManager->find($entryId);

        if (empty($entry)) {
            throw PageNotFoundException::forPageNotFound(
                lang('Admin.noEntryFoundWithIdx', ['x' => $entryId])
            );
        }

        // Map entry fields for easier access, e.g., hyper_component_elements, hyper_css, etc.
        $mappedEntryFields = map_entry_fields($entry['fields']);

        $this->loadUserCreatedComponents();

        /* View data */

        $this->data['entry'] = $entry;
        $this->data['mapped_entry_fields'] = $mappedEntryFields;

        $this->data['scripts'] = []; // Script assets
        $this->data['styles'] = []; // Style assets

        $this->data['title'] = lang('PagingSystem.editor-x', ['x' => (isset($mappedEntryFields['hyper_title']) ? $mappedEntryFields['hyper_title'] : 'Untitled')]);

        /* Filters */

        $this->data = $this->hooks->filter(hook('PagingSystemBackend.controller:editor:index:data'), $this->data);

        return render('\Modules\PagingSystem\Views\Admin\editor', $this->data);
    }

    /**
     * Load user created components
     * @todo Finish user-created components 
     */
    private function loadUserCreatedComponents()
    {
        // Get test user-created components for editor plugin blocks
        if (ENVIRONMENT !== 'production') {
            $testComponents = $this->entriesModel->stardust()->withLegacyAliases(true)->where('model_name', 'Component')->get()->getResultArray();
            foreach ($testComponents as &$row) {
                if (isset($row['fields'])) {
                    $fieldsArray = json_decode($row['fields'], true);
                    if (is_array($fieldsArray)) {
                        // Check if it's the legacy list of objects format
                        if (array_is_list($fieldsArray) && !empty($fieldsArray) && isset($fieldsArray[0]['id'])) {
                            foreach ($fieldsArray as $field) {
                                if (isset($field['id']) && isset($field['value'])) {
                                    $row[$field['id']] = $field['value'];
                                }
                            }
                        } else {
                            // New format: key-value pairs
                            foreach ($fieldsArray as $key => $value) {
                                $row[$key] = $value;
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
