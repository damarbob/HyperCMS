<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;

class ModelsSettings extends AdminController
{
    public function index(): string
    {
        $this->data['title'] = lang('Admin.settings');

        return render('admin/settings_models', $this->data);
    }
}
