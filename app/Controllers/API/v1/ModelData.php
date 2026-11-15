<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;

class ModelData extends ApiController
{
    public function index()
    {
        // The saved versions of a model are for the group that manages models in the admin.
        if (! $this->caller()->inGroup('superadmin')) {
            return $this->deny(403, lang('Auth.notEnoughPrivilege'));
        }

        // The DataTables POST parameters go straight to the store.
        return $this->response->setJSON(service('modelStore')->versionsDataTable($this->request->getPost()));
    }
}
