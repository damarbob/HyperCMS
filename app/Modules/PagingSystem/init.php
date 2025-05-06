<?php

namespace Modules\PagingSystem;

use App\Controllers\API\v1\Model;
use App\Services\HyperHooks;
use App\Models\EntriesModel;
use App\Models\ModelsModel;
use DOMXPath;
use Masterminds\HTML5;

log_message('debug', 'Paging System module initialized.');

HyperHooks::getInstance()->register(hook('Core.modules:init'), function () {
    log_message('debug', 'Paging System module init hook triggered.');

    // Check which models are eligible for the page editor
    $modelsModel = new ModelsModel();

    $modelsBuilder = $modelsModel->getCustomBuilder();
    $models = $modelsBuilder
        ->get()
        ->getResultArray();

    $eligibleModelIds = []; // Page Editor eligible model IDs
    $eligibleModelNames = []; // Page Editor eligible model names

    $metaEligibleModelIds = []; // Meta eligible model IDs
    $metaEligibleModelNames = []; // Meta eligible model names

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
        // Eligible models must have the following fields:
        // - hyper_html
        // - hyper_css
        // - hyper_component_elements
        // - hyper_page_project_data
        // All of them must be of className hyper-code-field
        $hasHtml  = isset($fieldsById['hyper_html']) && $fieldsById['hyper_html']['className'] === 'hyper-code-field';
        $hasCss  = isset($fieldsById['hyper_css']) && $fieldsById['hyper_css']['className'] === 'hyper-code-field';
        $hasComponentElements  = isset($fieldsById['hyper_component_elements']) && $fieldsById['hyper_component_elements']['className'] === 'hyper-code-field';
        $hasPageProjectData  = isset($fieldsById['hyper_page_project_data']) && $fieldsById['hyper_page_project_data']['className'] === 'hyper-code-field';

        $hasMetaContent  = isset($fieldsById['meta_content']) && $fieldsById['meta_content'];
        $hasMetaPageId  = isset($fieldsById['meta_name']) && $fieldsById['meta_name'];

        // Check if the model is eligible for the page editor
        // If all required fields are present, add the model to the eligible list
        if ($hasHtml && $hasCss && $hasComponentElements && $hasPageProjectData) {
            $eligibleModelIds[] = $model['id'];
            $eligibleModelNames[] = $model['name'];
            log_message('debug', 'Paging System model is eligible for Editor: ' . $model['name']);
        }

        // Check if the model is eligible for meta
        // If all required fields are present, add the model to the eligible list
        if ($hasMetaContent && $hasMetaPageId) {
            $metaEligibleModelIds[] = $model['id'];
            $metaEligibleModelNames[] = $model['name'];
            log_message('debug', 'Paging System model is eligible for Meta: ' . $model['name']);
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
});

HyperHooks::getInstance()->register(hook('Backend.part:view:sidebar:settings'), function () {
    return view('Modules\PagingSystem\Views\Parts/menu_sidebar_settings');
});

HyperHooks::getInstance()->register(hook('Backend.view:entries:edit'), function ($model, $entry) {
    $pagingSystemEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');

    $eligibility = false; // Initial state

    if (in_array($model['id'], $pagingSystemEligibleModelIds)) {
        $eligibility = true;
    }

    if ($eligibility):
        return view('Modules\PagingSystem\Views\Parts\button_open_editor', [
            'entry' => $entry,
        ]);
    endif;
});

HyperHooks::getInstance()->register(hook('Backend.controller:entries:edit'), function ($data) {});

HyperHooks::getInstance()->register(hook('Backend.view:settings'), function () {});

HyperHooks::getInstance()->register(hook('Backend.controller:settings:update'), function ($request) {});

HyperHooks::getInstance()->register(hook('PagingSystemBackend.controller:frontend:index'), function ($path) {
    log_message('debug', 'Path: ' . $path);

    /** @var array */
    $pagingSystemEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');
    $primaryModelId = service('settings')->get('PagingSystem.primaryModelId'); // Default to 1 if not set

    // Make sure we have an array of eligible IDs.
    if (empty($pagingSystemEligibleModelIds) || !is_array($pagingSystemEligibleModelIds)) {
        return null;
    }

    // If no primary model ID is set, use the first eligible model ID.
    if (empty($primaryModelId)) {
        $primaryModelId = $pagingSystemEligibleModelIds[0];
    }

    // If the primary model ID is not in the eligible list, return null.
    if (!in_array($primaryModelId, $pagingSystemEligibleModelIds, true)) {
        return null;
    }

    $entriesModel = new EntriesModel();

    $pageEntriesModelBuilder = $entriesModel->getCustomBuilder();
    $pages = $pageEntriesModelBuilder
        ->whereIn('model_id', $pagingSystemEligibleModelIds) // Get all eligible models
        ->get()
        ->getResultArray();

    $isPageExists = false;

    foreach ($pages as $page) {
        if ($page['model_id'] !== $primaryModelId) {
            continue; // Skip if the model ID doesn't match the primary model ID
        }

        $fields = array_column(json_decode($page['fields']), 'value', 'id');

        // Mandatory fields
        $pageTitle = $fields['hyper_title'];
        $pageCss = $fields['hyper_css'];
        $pageHtml = $fields['hyper_html'];
        $pageUrl = isset($fields['hyper_page_url']) ? $fields['hyper_page_url'] : null; // @TODO: Decide whether this is mandatory/optional

        // Optional fields
        $pageHook = isset($fields['hyper_page_hook_id']) ? $fields['hyper_page_hook_id'] : null;

        if ($pageHook === hook('Frontend.main')) {
            if ($pageUrl) {
                if ($pageUrl !== $path) {
                    continue;
                }
            } elseif ($pageTitle) {
                if (url_title($pageTitle, '-', true) !== url_title($path, '-', true)) {
                    continue;
                }
            } else {
                // Neither URL nor title provided – decide on a default behavior
                continue;
            }

            $isPageExists = true;

            // Store the title in the head with the first priority
            HyperHooks::getInstance()->register(hook('Frontend.head'), function () use ($pageTitle) {
                return "<title>$pageTitle</title>";
            });
        }

        // Get the current request object
        $requestGet = service('request')->getGet();

        // Store the CSS in the head with higher priority
        HyperHooks::getInstance()->register(hook('Frontend.head'), function () use ($pageCss) {
            return "<style>$pageCss</style>";
        });

        if ($pageHook !== hook('Frontend.main')) {
            // Put the page's HTML content based on the hook set
            HyperHooks::getInstance()->register($pageHook, function () use ($pageHtml) {
                return $pageHtml;
            });
        }

        log_message('debug', "Showing page: $pageTitle");

        if ($pageHook !== hook('Frontend.main')) {
            continue;
        }

        // Initialize HTML5 parser
        $html5 = new HTML5([
            'disable_html_ns' => true, // Better for modern HTML
            'preserve_whitespace' => true,
        ]);

        // Load your HTML (assuming $html contains your input)
        $dom = $html5->loadHTML($pageHtml);

        // Your modification logic here
        foreach ($dom->getElementsByTagName('meta') as $meta) {

            // Dynamic meta tags
            // Check if the meta tag has the required attributes
            $hasHyperQueryModelId = $meta->hasAttribute('data-hyper-query-model-id');
            $hasHyperQueryFindField = $meta->hasAttribute('data-hyper-query-find-field');
            $hasHyperQueryFindValue = $meta->hasAttribute('data-hyper-query-find-value');
            $hasHyperQueryReturnField = $meta->hasAttribute('data-hyper-query-return-field');

            if (!$hasHyperQueryModelId || !$hasHyperQueryFindField || !$hasHyperQueryFindValue || !$hasHyperQueryReturnField) {
                continue; // Skip if any of the attributes are missing
            }

            $modelId = $meta->getAttribute('data-hyper-query-model-id');
            $findField = $meta->getAttribute('data-hyper-query-find-field');
            $findValue = $meta->getAttribute('data-hyper-query-find-value');
            $returnField = $meta->getAttribute('data-hyper-query-return-field');

            switch ($findValue) {
                case '{uri-query-param}':
                    $findValue = $requestGet[$findField]; // Get the value from the URL query parameter
                    break;
                case '':

                    break;
            }

            // Prepare the POST data
            $postData = [
                'id'   => $modelId,
                'find' => [
                    'field' => $findField,
                    'value' => $findValue
                ]
                // Add additional parameters if required (e.g., draw, start, length)
            ];

            $modelController = new Model();
            $metaData = $modelController->getModelData($postData)['data'];

            $meta->setAttribute('content', array_values($metaData)[0][$returnField]);
            $meta->removeAttribute('data-hyper-query-model-id');
            $meta->removeAttribute('data-hyper-query-find-field');
            $meta->removeAttribute('data-hyper-query-find-value');
            $meta->removeAttribute('data-hyper-query-return-field');
        }

        $htmlContent = $html5->saveHTML($dom);

        // Put the page's HTML content based on the hook set
        HyperHooks::getInstance()->register($pageHook, function () use ($htmlContent) {
            return $htmlContent;
        });
    }

    // If no page is found, return the default view.
    if (!$isPageExists) {
        // No matching page found—return a 404.
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    return view('Modules\PagingSystem\Views\frontend');
}, 9);

HyperHooks::getInstance()->register(hook('PagingSystemBackend.controller:frontend:index:pagehtml'), function ($pageHtml) {
    // Initialize HTML5 parser
    $html5 = new HTML5([
        'disable_html_ns' => true, // Better for modern HTML
        'preserve_whitespace' => true,
    ]);

    // Load your HTML (assuming $html contains your input)
    $dom = $html5->loadHTML($pageHtml);

    // Ensure document structure exists
    $head = $dom->getElementsByTagName('head')->item(0);
    $body = $dom->getElementsByTagName('body')->item(0);

    if (!$head) {
        $head = $dom->createElement('head');
        $htmlElement = $dom->getElementsByTagName('html')->item(0) ?: $dom->appendChild($dom->createElement('html'));
        $htmlElement->insertBefore($head, $body ?? null);
    }

    if (!$body) {
        $body = $dom->createElement('body');
        $dom->documentElement->appendChild($body);
    }

    // Phase 1: Collect all elements in their original order
    $allNodes = [];
    $xpath = new DOMXPath($dom);

    // Get all meta and script nodes in document order
    foreach ($xpath->query('//meta') as $node) {
        $allNodes[] = [
            'node' => $node,
            'is_meta' => true,
            'is_head_script' => false
        ];
    }

    // Get all script nodes
    foreach ($xpath->query('//script') as $node) {
        $allNodes[] = [
            'node' => $node,
            'is_meta' => false,
            'is_head_script' => ($node->parentNode->nodeName === 'head')
        ];
    }

    // Phase 2: Reorganize nodes
    foreach ($allNodes as $item) {
        $node = $item['node'];

        if ($item['is_meta']) {
            // Move meta to head if not already there
            if ($node->parentNode !== $head) {
                $node->parentNode->removeChild($node);
                $head->appendChild($node);
            }
        } elseif ($item['is_head_script']) {
            // Keep head scripts in head
            continue;
        } else {
            // Move other scripts to end of body
            if ($node->parentNode !== $body) {
                $node->parentNode->removeChild($node);
                $body->appendChild($node);
            }
        }
    }

    // Save the modified HTML
    $modifiedHtml = $html5->saveHTML($dom);

    return $modifiedHtml;
});
