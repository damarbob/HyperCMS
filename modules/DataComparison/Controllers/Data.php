<?php

namespace DataComparison\Controllers;

use App\Filters\ApiAuthFilter;
use App\Modules\ModuleController;

/**
 * The data of the source that is this site: the fields of the entries that are asked for, as `{id, fields}` rows. A person
 * who is signed in reads it through the admin, and a token reads it at the address of the earlier versions. Either way only
 * entries of models that the caller may read come back.
 */
class Data extends ModuleController
{
    public function index()
    {
        $caller = ApiAuthFilter::caller() ?? auth()->user();
        if ($caller === null || ! service('permissions')->can($caller, 'entries.browse')) {
            return $this->json(['message' => lang('Auth.notEnoughPrivilege')], 403);
        }

        $post = $this->request->getPost();

        // Newest first, narrowed by whichever of entry id, entry ids and model id were sent.
        $rows = service('entryStore')->recent([
            'id'               => $post['entry_id'] ?? null,
            'ids'              => $post['entry_ids'] ?? null,
            'model_id'         => $post['model_id'] ?? null,
            'hidden_model_ids' => service('modelAccess')->hiddenIds($caller),
        ]);

        return $this->json(['data' => array_map(static function (array $row): array {
            $flat = json_decode((string) ($row['fields'] ?? '[]'), true) ?: [];
            // The earlier list of {id, value} is read as well as the map of id to value.
            if (array_is_list($flat) && $flat !== [] && isset($flat[0]['id'])) {
                $flat = array_column($flat, 'value', 'id');
            }

            return ['id' => $row['id'], 'fields' => $flat];
        }, $rows)]);
    }
}
