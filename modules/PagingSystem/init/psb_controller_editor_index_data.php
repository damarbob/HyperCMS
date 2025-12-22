<?php

use StarCore\Service\HyperHooks;

// Editor controller index data filter
HyperHooks::getInstance()->register(hook('PagingSystemBackend.controller:editor:index:data'), function ($data) {

    /** @var \StarDust\Models\EntriesModel */
    $entriesModel = model('entriesModel');

    /** @var array */
    $pagingSystemAssetsEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_assets_eligible_model_ids');
    $assetsModelId = service('settings')->get('PagingSystem.assetsModelId');

    // Make sure we have an array of eligible IDs.
    if (empty($pagingSystemAssetsEligibleModelIds) || !is_array($pagingSystemAssetsEligibleModelIds)) {
        return $data;
    }

    // If no assets model ID is set, use the first assets-eligible model ID.
    if (empty($assetsModelId)) {
        $assetsModelId = $pagingSystemAssetsEligibleModelIds[0];
    }

    // If the assets model ID is not in the eligible list, return.
    if (!in_array($assetsModelId, $pagingSystemAssetsEligibleModelIds, true)) {
        return $data;
    }

    $assets = $entriesModel->getCustomBuilder()
        ->where('model_id', $assetsModelId)
        ->get()
        ->getResultArray();

    if ($assets) {
        foreach ($assets as $asset) {

            // Map fields - handle both old and new formats
            $fieldsArray = json_decode($asset['fields'], JSON_UNESCAPED_SLASHES);
            if (is_array($fieldsArray) && array_is_list($fieldsArray) && !empty($fieldsArray) && isset($fieldsArray[0]['id'])) {
                $fields = array_column($fieldsArray, 'value', 'id');
            } else {
                $fields = $fieldsArray;
            }

            $url = $fields['asset_url'];
            $type = $fields['asset_type'];
            $placement = $fields['asset_placement'];

            switch ($type) {
                case 'script':
                    $data['scripts'][$placement][] = $url;
                    break;
                case 'style':
                    $data['styles'][$placement][] = $url;
                    break;
            }
        }
    }

    return $data;
});
