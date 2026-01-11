<?php

use StarCore\Service\HyperHooks;
use App\Controllers\API\v1\Model;
use Masterminds\HTML5;

// Frontend controller index
HyperHooks::getInstance()->register(hook('PagingSystemBackend.controller:frontend:index'), function ($path) {
    log_message('debug', 'Path: ' . $path);

    /** @var \StarDust\Models\EntriesModel */
    $entriesModel = model('entriesModel');

    /* Page-eligible model check */

    // Retrieve eligible and primary model IDs.
    $eligible = pagingSystemGetEligible('paging_system_eligible_model_ids', 'PagingSystem.primaryModelId');

    /** @var array */
    $pagingSystemEligibleModelIds = $eligible['eligible'];
    $primaryModelId = $eligible['primary'];

    // Validate that we have both eligible models and a primary model.
    if (!is_array($pagingSystemEligibleModelIds) || empty($pagingSystemEligibleModelIds) || empty($primaryModelId)) {
        return null;
    }

    // Instantiate EntriesModel and retrieve pages for eligible model IDs.
    $pages = $entriesModel->stardust()->withLegacyAliases(true)
        ->whereIn('model_id', $pagingSystemEligibleModelIds)
        ->get()
        ->getResultArray();

    /* End of page-eligible model check */

    /* Assets-eligible model check */

    // Retrieve eligible and assets model IDs.
    $assetsEligible = pagingSystemGetEligible('paging_system_assets_eligible_model_ids', 'PagingSystem.assetsModelId');

    /** @var array */
    $assetsEligibleModelIds = $assetsEligible['eligible'];
    $assetsModelId = $assetsEligible['primary'];
    $assets = [];

    // Validate that we have both eligible models and a primary model.
    if (is_array($assetsEligibleModelIds) && !empty($assetsEligibleModelIds) && !empty($assetsModelId)) {
        $assets = $entriesModel->stardust()->withLegacyAliases(true)
            ->whereIn('model_id', $assetsEligibleModelIds)
            ->get()
            ->getResultArray();
    }

    /* End of assets-eligible model check */

    // Prepare some frequently used values.
    $isPageExists = false;
    $frontendMainHook = hook('Frontend.main');
    $frontendHeadHook = hook('Frontend.head');
    $frontendFooterHook = hook('Frontend.footer');

    /* Loop assets */
    foreach ($assets as $asset) {

        // Process only assets that match the primary model ID.
        if ($asset['model_id'] !== $assetsModelId) {
            continue;
        }

        $fieldsArray = json_decode($asset['fields'], JSON_UNESCAPED_SLASHES);
        // Handle both old format [{'id': x, 'value': y}] and new format {x: y}
        if (is_array($fieldsArray) && array_is_list($fieldsArray) && !empty($fieldsArray) && isset($fieldsArray[0]['id'])) {
            $fields = array_column($fieldsArray, 'value', 'id');
        } else {
            $fields = $fieldsArray;
        }

        $type = $fields['asset_type'];
        $url = $fields['asset_url'];
        $placement = $fields['asset_placement'];

        // Set the hook based on the placement
        $hook = $placement === 'head' ?
            $frontendHeadHook : (
                $placement === 'body' ?
                $frontendFooterHook :
                $frontendMainHook
            );

        // Register the hook
        HyperHooks::getInstance()->register($hook, function () use ($type, $url) {
            switch ($type) {
                case "style":
                    return "<link rel='stylesheet' href='{$url}'>";
                case "script":
                    return "<script type='text/javascript' src='{$url}'></script>";
            }
        });
    }
    /* End of loop assets */

    /* Loop pages */
    foreach ($pages as $page) {

        // Process only pages that match the primary model ID.
        if ($page['model_id'] !== $primaryModelId) {
            continue;
        }

        // Decode fields JSON and map field values by their IDs.
        $fieldsArray = json_decode($page['fields'], true);
        // Handle both old format [{'id': x, 'value': y}] and new format {x: y}
        if (is_array($fieldsArray) && array_is_list($fieldsArray) && !empty($fieldsArray) && isset($fieldsArray[0]['id'])) {
            $fields = array_column($fieldsArray, 'value', 'id');
        } else {
            $fields = $fieldsArray;
        }

        // Retrieve mandatory fields.
        $pageTitle = $fields['hyper_title'] ?? null;
        $pageCss = $fields['hyper_css'] ?? '';
        $pageHtml = $fields['hyper_html'] ?? '';
        $pageUrl = $fields['hyper_page_url'] ?? null;
        // Optional field.
        $pageHook = $fields['hyper_page_hook_id'] ?? null;

        // If the page uses the Frontend.main hook, perform URL/title matching.
        if ($pageHook === $frontendMainHook) {
            // If URL is provided, it must match the current path.
            if ($pageUrl && $pageUrl !== $path) {
                continue;
            }
            // Otherwise, if title is provided, compare a URL-friendly version.
            if (!$pageUrl && $pageTitle) {
                if (url_title($pageTitle, '-', true) !== url_title($path, '-', true)) {
                    continue;
                }
            }
            // If neither URL nor title are valid, skip this page.
            if (!$pageUrl && !$pageTitle) {
                continue;
            }

            $isPageExists = true; // The page is exists

            // Register the page title in the head.
            HyperHooks::getInstance()->register($frontendHeadHook, function () use ($pageTitle) {
                return "<title>$pageTitle</title>";
            });
        }

        // Always register the page's CSS in the head.
        HyperHooks::getInstance()->register($frontendHeadHook, function () use ($pageCss, $assets) {
            $style = "<style>$pageCss</style>";
            return $style;
        });

        // For non-main hooks, simply register the page's HTML content.
        if (!empty($pageHook) && $pageHook !== $frontendMainHook) {
            HyperHooks::getInstance()->register($pageHook, function () use ($pageHtml) {
                return $pageHtml;
            });
            log_message('debug', "Showing page: $pageTitle");
            continue;
        }

        /* Dynamic meta-tag processing */

        // Initialize HTML5 parser with appropriate options.
        $html5 = new HTML5([
            'disable_html_ns' => true,
            'preserve_whitespace' => true,
        ]);

        // Load the page HTML into a DOMDocument.
        $dom = $html5->loadHTML($pageHtml);

        pagingSystemProcessDynamicMetaTags($dom); // Process dynamic meta tags

        // Save the modified HTML.
        $htmlContent = $html5->saveHTML($dom);

        /* End of dynamic meta-tag processing */

        // Register the modified HTML to be output via the main hook.
        HyperHooks::getInstance()->register($frontendMainHook, function () use ($htmlContent) {
            return $htmlContent;
        });

        log_message('debug', "Showing page: $pageTitle");
    }
    /* End of loop pages */

    if (!$isPageExists) {
        // If no matching page was found, throw a 404 error.
        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    return view('Modules\PagingSystem\Views\frontend');
}, 9);

/**
 * Retrieve and validate the primary model ID from settings and eligible model IDs from state.
 *
 * If the primary model ID is not set or empty, the function defaults it to the first eligible model ID.
 * It then returns both the primary model ID (if valid) and the full list of eligible model IDs.
 *
 * @param string $stateKey   The key to retrieve the eligible model IDs (e.g., 'paging_system_eligible_model_ids').
 * @param string $settingKey The key to retrieve the primary model ID from settings (e.g., 'PagingSystem.primaryModelId').
 *
 * @return array Returns an associative array with keys:
 *               - 'primary': the validated primary model ID or null if invalid.
 *               - 'eligible': the array of eligible model IDs.
 */
function pagingSystemGetEligible(string $stateKey, string $settingKey): array
{
    // Retrieve eligible model IDs from state.
    $eligibleModelIds = HyperHooks::getInstance()->getState($stateKey);
    // Retrieve the primary model ID from settings.
    $primaryModelId = service('settings')->get($settingKey);

    // Ensure we have a valid, non-empty array of eligible model IDs.
    if (!is_array($eligibleModelIds) || empty($eligibleModelIds)) {
        return [
            'primary' => null,
            'eligible' => []
        ];
    }

    // Default the primary model ID to the first eligible element if it's empty.
    $primaryModelId = $primaryModelId ?: reset($eligibleModelIds);

    // Validate that the primary model ID exists within the eligible model IDs.
    if (!in_array($primaryModelId, $eligibleModelIds, true)) {
        return [
            'primary' => null,
            'eligible' => $eligibleModelIds,
        ];
    }

    return [
        'primary' => $primaryModelId,
        'eligible' => $eligibleModelIds,
    ];
}

/**
 * Process dynamic meta tags in a DOMDocument.
 *
 * This function iterates over all <meta> tags in the provided DOM, checks if they have the required
 * data attributes for dynamic processing, performs a model lookup to retrieve dynamic content, and then
 * removes the temporary data attributes.
 *
 * @param DOMDocument $dom       The DOM document (or element) containing the meta tags.
 * @param array       $requestGet The GET parameters from the current request.
 *
 * @return void
 */
function pagingSystemProcessDynamicMetaTags($dom): void
{
    /** @var \CodeIgniter\HTTP\CLIRequest|\CodeIgniter\HTTP\IncomingRequest */
    $request = service('request');
    $requestGet = $request->getGet();

    // Iterate over all <meta> tags in the DOM.
    foreach ($dom->getElementsByTagName('meta') as $meta) {
        // Ensure all required data attributes are present.
        if (
            !$meta->hasAttribute('data-hyper-query-model-id') ||
            !$meta->hasAttribute('data-hyper-query-find-field') ||
            !$meta->hasAttribute('data-hyper-query-find-value') ||
            !$meta->hasAttribute('data-hyper-query-return-field')
        ) {
            continue;
        }

        // Retrieve the data attributes.
        $modelId = $meta->getAttribute('data-hyper-query-model-id');
        $findField = $meta->getAttribute('data-hyper-query-find-field');
        $findValue = $meta->getAttribute('data-hyper-query-find-value');
        $returnField = $meta->getAttribute('data-hyper-query-return-field');

        // Replace placeholder value with the appropriate value from the GET query.
        if ($findValue === '{uri-query-param}') {
            $findValue = $requestGet[$findField] ?? null;
        }

        // Prepare the data for the model lookup.
        $postData = [
            'id' => $modelId,
            'find' => [
                'field' => $findField,
                'value' => $findValue,
            ]
        ];

        // Instantiate the model controller and retrieve the dynamic meta data.
        $modelController = new Model();
        $metaData = $modelController->getModelData($postData)['data'];

        // Assume the first matching entry contains the desired field.
        $metaValues = array_values($metaData);
        if (!empty($metaValues) && isset($metaValues[0][$returnField])) {
            $meta->setAttribute('content', $metaValues[0][$returnField]);
        }

        // Remove the auxiliary data attributes.
        $meta->removeAttribute('data-hyper-query-model-id');
        $meta->removeAttribute('data-hyper-query-find-field');
        $meta->removeAttribute('data-hyper-query-find-value');
        $meta->removeAttribute('data-hyper-query-return-field');
    }
}
