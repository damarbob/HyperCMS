<?php

namespace App\Controllers\API\v1;

use App\Controllers\API\v1\ApiController;

class Model extends ApiController
{
    public function index()
    {
        // The DataTables POST parameters go straight to the store.
        return $this->response->setJSON($this->getModelData($this->request->getPost()));
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
