<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class FileManager extends BaseController
{

    public function index()
    {
        $this->data['title'] = lang('Admin.fileManager');
        return view('admin/file_manager', $this->data);
    }
}
