<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Data\LookupException;

class EntryData extends ApiController
{
    public function index()
    {
        $post = $this->request->getPost();

        // Only a plain whole number is taken as an id, so the id that is checked below is the id that is read.
        $entryId = $post['id'] ?? null;
        if ($entryId !== null && $entryId !== '' && (! is_string($entryId) || ! ctype_digit($entryId))) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'The entry id must be a whole number.']);
        }

        // An entry's versions are as private as its model. An entry that does not exist falls through to the 400 below.
        if ($entryId !== null && $entryId !== '') {
            $modelId = service('entryStore')->modelIdOf($entryId);
            if ($modelId !== null && ! service('modelAccess')->canRead($this->caller(), $modelId)) {
                return $this->deny(403, lang('Auth.notEnoughPrivilege'));
            }
        }

        try {
            // The DataTables POST parameters go straight to the store.
            $output = service('entryStore')->versionsDataTable($post);
        } catch (LookupException $e) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => $e->getMessage()]);
        }

        return $this->response->setJSON($output);
    }
}
