<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class ModelsSettings extends BaseController
{
    public function index(): string
    {
        $this->data['title'] = lang('Admin.settings');

        return view('admin/settings_models', $this->data);
    }
}
