<?php

namespace App\Controllers\API\v1;

class User extends ApiController
{
    function index()
    {
        $user = auth()->user();
        return $this->respond($user, 200);
    }
}
