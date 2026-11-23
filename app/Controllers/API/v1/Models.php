<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;

class Models extends ApiController
{
    public function index()
    {
        // Someone who manages models gets every model. Anyone else gets the models their roles may read, as in the admin menu.
        $caller = $this->caller();
        $hidden = service('permissions')->can($caller, 'models.manage') ? [] : service('modelAccess')->hiddenIds($caller);

        // The DataTables POST parameters go straight to the store.
        return $this->response->setJSON(service('modelStore')->dataTable($this->request->getPost(), $hidden));
    }
}
