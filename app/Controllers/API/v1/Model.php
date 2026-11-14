<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;

class Model extends ApiController
{
    public function index()
    {
        $post = $this->request->getPost();

        if ($this->caller() === null) {
            // A visitor may read a model that is marked public, and only its live entries.
            $modelId = $post['id'] ?? null;
            if (! is_string($modelId) || ! ctype_digit($modelId) || ! service('modelAccess')->isPublic($modelId)) {
                return $this->deny(401, lang('Filter.loginRequired'));
            }
            unset($post['trash']);
        }

        // The DataTables POST parameters go straight to the store.
        return $this->response->setJSON($this->getModelData($post));
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
