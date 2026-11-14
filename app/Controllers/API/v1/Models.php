<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;

class Models extends ApiController
{
    public function index()
    {
        // The DataTables POST parameters go straight to the store.
        return $this->response->setJSON(service('modelStore')->dataTable($this->request->getPost()));
    }
}
