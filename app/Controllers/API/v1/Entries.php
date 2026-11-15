<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Filters\ApiAuthFilter;

class Entries extends ApiController
{
    public function index()
    {
        // This list crosses every model, so it is for the groups that may open the admin entries page.
        if (! $this->caller()->inGroup(...ApiAuthFilter::STAFF_GROUPS)) {
            return $this->deny(403, lang('Auth.notEnoughPrivilege'));
        }

        // The DataTables POST parameters go straight to the store, which leaves out the models this caller may not read.
        $hidden = service('modelAccess')->hiddenIds($this->caller());

        return $this->response->setJSON(service('entryStore')->dataTable($this->request->getPost(), $hidden));
    }
}
