<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;

/**
 * The settings of the signed-in person, and for people who manage settings, the settings of the site.
 */
class Settings extends AdminController
{
    private const PER_PAGE = [10, 25, 50, 100];

    public function index()
    {
        return $this->form('', 200);
    }

    public function update()
    {
        $post    = $this->request->getPost();
        $perPage = (int) ($post['general_datatable_entries_per_page'] ?? 0);

        if (! in_array($perPage, self::PER_PAGE, true)) {
            return $this->form(lang('Admin.settingsInvalid'), 422);
        }

        service('settings')->set('App.datatableEntriesPerPage', $perPage, 'user:' . user_id());

        // The site settings are for the people who manage settings, and a form from anyone else cannot change them.
        if ($this->manages()) {
            service('settings')->set('Hyper.twoFactor', ! empty($post['two_factor']));
        }

        return redirect()->to(base_url('admin/settings'))->with('success', lang('Admin.settingsSuccessfullySaved'));
    }

    private function manages(): bool
    {
        try {
            return can('settings.manage');
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function form(string $error, int $status)
    {
        $this->data = array_merge($this->data, [
            'title'     => lang('Admin.settings'),
            'crumbs'    => [[lang('Admin.settings'), null]],
            'perPage'   => (int) (service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 25),
            'perPages'  => self::PER_PAGE,
            'manages'   => $this->manages(),
            'twoFactor' => (bool) service('settings')->get('Hyper.twoFactor'),
            'error'     => $error,
        ]);

        return $this->response->setStatusCode($status)->setBody(view('admin/settings', $this->data));
    }
}
