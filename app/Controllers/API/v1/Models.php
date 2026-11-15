<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;

class Models extends ApiController
{
    public function index()
    {
        // A superadmin manages every model. Anyone else gets the models their groups may read, as in the admin menu.
        $caller = $this->caller();
        $hidden = $caller->inGroup('superadmin') ? [] : service('modelAccess')->hiddenIds($caller);

        // The DataTables POST parameters go straight to the store.
        return $this->response->setJSON(service('modelStore')->dataTable($this->request->getPost(), $hidden));
    }
}
