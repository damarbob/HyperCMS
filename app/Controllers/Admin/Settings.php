<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Settings extends BaseController
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

    public function update($_)
    {
        /** @var HyperHooks $hooks */
        $hooks = service('hooks');

        /** @var \CodeIgniter\Validation\ValidationInterface */
        $validation = service('validation');

        $context = 'user:' . user_id();

        // Hook for update settings
        // Passes the request to the hook
        $updateResponse = $hooks->trigger(hook('Backend.controller:settings:update'), [$this->request]);

        $data = $this->request->getPost();

        $validation->setRules([
            'general_datatable_entries_per_page' => [
                'label' => lang('Admin.datatableEntriesPerPage'),
                'rules' => 'required',
            ],
        ]);

        if (!$validation->run($data)) {
            return redirect()->back()->withInput();
        }

        service('settings')->set('App.datatableEntriesPerPage', $data['general_datatable_entries_per_page'], $context);

        return $updateResponse ?? redirect('admin/settings')->with('success', lang('Admin.settingsSuccessfullySaved'));
    }
}
