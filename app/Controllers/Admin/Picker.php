<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Fields\FieldTypes;
use App\Libraries\EntryPicker;

/**
 * The entries of a model for the pickers of the entry form, as JSON. A person who may read the entries of a model may
 * look them up, and no one else.
 */
class Picker extends AdminController
{
    public function entries($modelId)
    {
        $model = is_numeric($modelId) ? service('modelStore')->findArray((int) $modelId) : false;
        if (! $model || ! can('entries.read', (int) $modelId)) {
            return $this->response->setStatusCode(403)->setJSON(['items' => [], 'more' => false, 'error' => lang('Auth.notEnoughPrivilege')]);
        }

        $q      = is_string($this->request->getGet('q')) ? mb_substr(trim($this->request->getGet('q')), 0, 100) : '';
        $answer = (new EntryPicker(FieldTypes::core()))->search($model, $q);

        return $this->response->setJSON($answer);
    }
}
