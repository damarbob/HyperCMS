<?php

use App\Services\HyperHooks;

// Init
HyperHooks::getInstance()->register(hook('Core.modules:init'), function () {
    log_message('debug', 'Paging System module init hook triggered.');

    // Check which models are eligible for the page editor
    /** @var \App\Models\ModelsModel */
    $modelsModel = model('modelsModel');

    $modelsBuilder = $modelsModel->getCustomBuilder();
    $models = $modelsBuilder
        ->get()
        ->getResultArray();

    $eligibleModelIds = []; // Page Editor eligible model IDs
    $eligibleModelNames = []; // Page Editor eligible model names

    $metaEligibleModelIds = []; // Meta eligible model IDs
    $metaEligibleModelNames = []; // Meta eligible model names

    $assetsEligibleModelIds = []; // Assets eligible model IDs
    $assetsEligibleModelNames = []; // Assets eligible model names

    foreach ($models as $model) {
        $fields = json_decode($model['fields'], true);

        if (empty($fields) || !is_array($fields)) continue;

        // Build a mapping: key -> content array
        $fieldsById = [];
        foreach ($fields as $element) {
            if (isset($element['id'])) {
                $fieldId = $element['id'];
                $fieldsById[$fieldId] = $element;
            }
        }

        // Now check our required fields
        // Editor-eligible models must have the following fields:
        // - hyper_html
        // - hyper_css
        // - hyper_component_elements
        // - hyper_page_project_data
        // All of them must contain a hyper-code-field className
        $hasHtml  = isset($fieldsById['hyper_html']) && $fieldsById['hyper_html']['className'] === 'hyper-code-field';
        $hasCss  = isset($fieldsById['hyper_css']) && $fieldsById['hyper_css']['className'] === 'hyper-code-field';
        $hasComponentElements  = isset($fieldsById['hyper_component_elements']) && $fieldsById['hyper_component_elements']['className'] === 'hyper-code-field';
        $hasPageProjectData  = isset($fieldsById['hyper_page_project_data']) && $fieldsById['hyper_page_project_data']['className'] === 'hyper-code-field';

        // Meta eligible models must have the following fields:
        // - meta_name
        // - meta_content
        $hasMetaContent  = isset($fieldsById['meta_content']) && $fieldsById['meta_content'];
        $hasMetaPageId  = isset($fieldsById['meta_name']) && $fieldsById['meta_name'];

        // Assets eligible models must have the following fields:
        // - asset_url
        // - asset_type
        // - asset_placement
        $hasAssetUrl = isset($fieldsById['asset_url']) && $fieldsById['asset_url'];
        $hasAssetType = isset($fieldsById['asset_type']) && $fieldsById['asset_type'];
        $hasAssetPlacement = isset($fieldsById['asset_placement']) && $fieldsById['asset_placement'];

        // Check if the model is eligible for the page editor
        // If all required fields are present, add the model to the eligible list
        if ($hasHtml && $hasCss && $hasComponentElements && $hasPageProjectData) {
            $eligibleModelIds[] = $model['id'];
            $eligibleModelNames[] = $model['name'];
            log_message('debug', 'Paging System: Model is eligible for Editor: ' . $model['name']);
        }

        // Check if the model is eligible for meta
        // If all required fields are present, add the model to the eligible list
        if ($hasMetaContent && $hasMetaPageId) {
            $metaEligibleModelIds[] = $model['id'];
            $metaEligibleModelNames[] = $model['name'];
            log_message('debug', 'Paging System: Model is eligible for Meta: ' . $model['name']);
        }

        // Check if the model is eligible for assets
        if ($hasAssetUrl && $hasAssetType && $hasAssetPlacement) {
            $assetsEligibleModelIds[] = $model['id'];
            $assetsEligibleModelNames[] = $model['name'];
            log_message('debug', 'Paging System: Model is eligible for Assets: ' . $model['name']);
        }
    }

    // Store the eligible model IDs and names in the HyperHooks state
    HyperHooks::getInstance()->setState(
        'paging_system_eligible_model_ids',
        $eligibleModelIds
    );
    HyperHooks::getInstance()->setState(
        'paging_system_eligible_model_names',
        $eligibleModelNames
    );
    HyperHooks::getInstance()->setState(
        'paging_system_meta_eligible_model_ids',
        $metaEligibleModelIds
    );
    HyperHooks::getInstance()->setState(
        'paging_system_meta_eligible_model_names',
        $metaEligibleModelNames
    );
    HyperHooks::getInstance()->setState(
        'paging_system_assets_eligible_model_ids',
        $assetsEligibleModelIds
    );
    HyperHooks::getInstance()->setState(
        'paging_system_assets_eligible_model_names',
        $assetsEligibleModelNames
    );

    /** @var \Codeigniter\Settings\Settings */
    $settings = service('settings');

    /* If the setting is empty, add first eligible model id to the setting */

    if (empty($settings->get('PagingSystem.primaryModelId')) && !empty($eligibleModelIds)) {
        $settings->set('PagingSystem.primaryModelId', $eligibleModelIds[0]);
        log_message('info', 'Paging System: Primary model ID set to ' . $eligibleModelIds[0]);
    }

    if (empty($settings->get('PagingSystem.metaModelId')) && !empty($metaEligibleModelIds)) {
        $settings->set('PagingSystem.metaModelId', $metaEligibleModelIds[0]);
        log_message('info', 'Paging System: Meta model ID set to ' . $metaEligibleModelIds[0]);
    }

    if (empty($settings->get('PagingSystem.assetsModelId')) && !empty($assetsEligibleModelIds)) {
        $settings->set('PagingSystem.assetsModelId', $assetsEligibleModelIds[0]);
        log_message('info', 'Paging System: Assets model ID set to ' . $assetsEligibleModelIds[0]);
    }
});
