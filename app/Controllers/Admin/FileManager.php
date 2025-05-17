<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class FileManager extends BaseController
{
    protected $helpers = ['hyper_hex'];

    public function index()
    {
        // Get the current request instance
        $request = service('request');

        $requesterId = $this->request->getGet('requester_id'); // The requester's ID for security

        // Send the requesterId if exists
        $this->data['requesterId'] = $requesterId ?? '';
        // Get the URI string
        $this->data['currentRoute'] = $request->getUri()->getPath();

        $this->data['title'] = lang('Admin.fileManager');

        return render('admin/file_manager', $this->data);
    }
}
