<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;

class Entries extends ApiController
{
    public function index()
    {
        // The DataTables POST parameters go straight to the store.
        return $this->response->setJSON(service('entryStore')->dataTable($this->request->getPost()));
    }
}
