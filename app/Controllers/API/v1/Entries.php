<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Data\Entries\ScanLimitExceeded;
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

        $post = $this->request->getPost();

        try {
            return $this->response->setJSON(service('entryStore')->dataTable($post, $hidden));
        } catch (ScanLimitExceeded $e) {
            // DataTables shows the text of an `error` key to the person using the table, in place of the rows.
            return $this->response->setJSON(['draw' => (int) ($post['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()]);
        }
    }
}
