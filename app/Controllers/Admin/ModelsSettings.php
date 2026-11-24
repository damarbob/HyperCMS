<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;

class ModelsSettings extends AdminController
{
    public function index(): string
    {
        $this->data['title']  = lang('Admin.settings');
        $this->data['crumbs'] = [[lang('Admin.settings'), base_url('admin/settings')], [lang('Admin.models'), null]];

        return view('admin/settings_models', $this->data);
    }
}
