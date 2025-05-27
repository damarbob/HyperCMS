<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;

class Settings extends AdminController
{
    public function index(): string
    {
        /** @var HyperHooks $hooks */
        $hooks = service('hooks');

        $this->data['title'] = lang('Admin.settings');

        // Filtered data
        // Passes controller data to the hook
        $this->data = $hooks->filter(hook('Backend.controller:settings:data'), $this->data);

        // Hook for update settings
        // Passes the request to the hook
        $hooks->trigger(hook('Backend.controller:settings'), [$this->data]);

        return render('admin/settings', $this->data);
    }

    public function update()
    {
        $context = 'user:' . user_id(); // Settings context

        $data = $this->request->getPost();

        $rules = [
            'general_datatable_entries_per_page' => [
                'label' => lang('Admin.datatableEntriesPerPage'),
                'rules' => 'required',
            ],
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        service('settings')->set('App.datatableEntriesPerPage', $data['general_datatable_entries_per_page'], $context);

        return $this->respond(lang('Admin.settingsSuccessfullySaved'), 'admin/settings');
    }
}
