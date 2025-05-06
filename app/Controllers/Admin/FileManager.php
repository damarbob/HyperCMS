<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class FileManager extends BaseController
{
    protected $helpers = ['hyper_hex'];

    public function index()
    {
        $requesterId = $this->request->getGet('requester_id'); // The requester's ID for security

        // Send the requesterId if exists
        $this->data['requesterId'] = $requesterId ?? '';

        $this->data['title'] = lang('Admin.fileManager');

        return view('admin/file_manager', $this->data);
    }
}
