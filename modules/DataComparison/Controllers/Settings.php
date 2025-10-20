<?php

namespace DataComparison\Controllers;

use App\Controllers\AdminController;

class Settings extends AdminController
{
    protected $helpers = ['hyper', 'hyper_url'];

    public function index()
    {
        /** @var \DataComparison\Config\DataComparison $config */
        $config = config('DataComparison');

        // Read raw JSON from config
        $raw = trim($config->defaultDataSources ?? '');

        // Fallback empty array if no value provided
        if ($raw === '') {
            $defaultDataSources = json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Log invalid JSON and fall back to empty array
                log_message('warning', 'Invalid JSON in defaultDataSources: ' . json_last_error_msg());
                $defaultDataSources = json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                // Re-encode with pretty print
                $defaultDataSources = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }

        $this->data['title'] = lang('Dc.moduleName') . ' ' . lang('Admin.settings');

        $this->data['dataSources'] = service('settings')->get('DataComparison.dataSources', 'user:' . user_id()) ?: $defaultDataSources;

        return render('\DataComparison\Views\settings', $this->data);
    }

    public function update()
    {
        $context = 'user:' . user_id(); // Settings context

        $data = $this->request->getPost();

        $rules = [
            'dataSources' => [
                'label' => lang('Dc.dataSources'),
                'rules' => 'required',
            ],
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        service('settings')->set('DataComparison.dataSources', $data['dataSources'], $context);

        return $this->respond(lang('Admin.settingsSuccessfullySaved'), 'admin/settings/data-comparison');
    }

    // Load the JSON states
    public function loadState()
    {
        $context = 'user:' . user_id(); // Settings context
        $states = service('settings')->get('DataComparison.states', $context) ?: json_encode([]);
        return $this->respond($states);
    }

    // Save the JSON states
    public function saveState()
    {
        $data = $this->request->getPost();

        $rules = [
            'states' => [
                'label' => lang('Dc.states'),
                'rules' => 'required',
            ],
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), statusCode: 400, withInput: false, success: false);
        }

        $context = 'user:' . user_id(); // Settings context
        $states = $data['states'] ?? json_encode([]);

        // Validate JSON and prevent potential security issues
        $decodedStates = json_decode($states, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->respond(lang('Dc.invalidJsonFormatForStates'), statusCode: 400, withInput: false, success: false);
        }

        // Re-encode to ensure clean JSON
        $states = json_encode($decodedStates);

        service('settings')->set('DataComparison.states', $states, $context);
        return $this->respond(lang('Admin.settingsSuccessfullySaved'), 'admin/settings/data-comparison');
    }

    // Get the last used state
    public function getLastUsedState()
    {
        $context = 'user:' . user_id(); // Settings context
        $state = service('settings')->get('DataComparison.lastUsedState', $context) ?: "";
        return $this->respond($state);
    }

    // Save the last used state
    public function saveLastUsedState()
    {
        $data = $this->request->getPost();

        $rules = [
            'state' => [
                'label' => lang('Dc.lastUsedState'),
                'rules' => 'required',
            ],
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), statusCode: 400, withInput: false, success: false);
        }

        $context = 'user:' . user_id(); // Settings context
        service('settings')->set('DataComparison.lastUsedState', $data['state'], $context);
        return $this->respond(lang('Admin.settingsSuccessfullySaved'), 'admin/settings/data-comparison');
    }
}
