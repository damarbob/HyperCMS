<?php

namespace App\Controllers\App;

use App\Controllers\BaseController;

class App extends BaseController
{
    public function index(): string
    {
        // Display the admin dashboard view
        return view('app');
    }
}
