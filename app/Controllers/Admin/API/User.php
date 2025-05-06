<?php

namespace App\Controllers\Admin\API;

use App\Controllers\API\v1\ApiController;

class User extends ApiController
{
    function index()
    {
        $user = auth()->user();
        return $this->respond($user, 200);
    }
}
