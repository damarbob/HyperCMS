<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Editor extends BaseController
{
    public function index(): string
    {
        // Display the admin dashboard view
        return view('admin/editor', $this->data);
    }
}
