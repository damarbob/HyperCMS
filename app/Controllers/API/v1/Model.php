<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;
use App\Data\Entries\ScanLimitExceeded;

class Model extends ApiController
{
    public function index()
    {
        $post = $this->request->getPost();

        $modelId = $post['id'] ?? null;

        // Only a plain whole number is taken as an id, so the id that is checked below is the id that is read.
        if ($modelId !== null && (! is_string($modelId) || ! ctype_digit($modelId))) {
            return $this->deny(400, lang('Filter.invalidResource'));
        }

        if ($this->caller() === null) {
            // A visitor may read a model that is marked public, and only its live entries.
            if ($modelId === null || ! service('modelAccess')->isPublic($modelId)) {
                return $this->deny(401, lang('Filter.loginRequired'));
            }
            unset($post['trash']);
        } elseif ($modelId === null) {
            return $this->deny(400, lang('Filter.invalidResource'));
        } elseif (! service('modelAccess')->canRead($this->caller(), $modelId)) {
            // The model's user groups decide, as they do on its admin page.
            return $this->deny(403, lang('Auth.notEnoughPrivilege'));
        }

        try {
            // The DataTables POST parameters go straight to the store.
            return $this->response->setJSON($this->getModelData($post));
        } catch (ScanLimitExceeded $e) {
            // DataTables shows the text of an `error` key to the person using the table, in place of the rows.
            return $this->response->setJSON(['draw' => (int) ($post['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get the entries of one model, from POST values or from direct parameters.
     *
     * Other modules call this method, so it stays and hands the work to the store.
     *
     * @param array $params The values to read (id, draw, start, length, search, order, columns, find, trash).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     */
    public function getModelData(array $params): array
    {
        return service('entryStore')->modelDataTable($params);
    }
}
