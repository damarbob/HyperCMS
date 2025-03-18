<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index(): string
    {
        // Display the admin dashboard view
        return view('admin/dashboard', $this->data);
    }
}
