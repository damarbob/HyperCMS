<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Data\LookupException;

class EntryData extends ApiController
{
    public function index()
    {
        try {
            // The DataTables POST parameters go straight to the store.
            $output = service('entryStore')->versionsDataTable($this->request->getPost());
        } catch (LookupException $e) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => $e->getMessage()]);
        }

        return $this->response->setJSON($output);
    }
}
