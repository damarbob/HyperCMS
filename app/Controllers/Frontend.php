<?php

namespace App\Controllers;

use App\Libraries\HyperHooks;

class Frontend extends BaseController
{
    public function index($path = 'home'): string
    {
        /* @TEST: Register page hooks */

        $pageEntriesModelBuilder = $this->entriesModel->getCustomBuilder();

        // @TEST: Get pages from user-generated entries
        // @IMPORTANT: Create settings which model will be used as 'Page' so we don't need to hardcode the model_id
        $pages = $pageEntriesModelBuilder
            ->where('model_id', 1)
            ->get()
            ->getResultArray();

        $isPageExists = false;

        foreach ($pages as $page) {
            $fields = array_column(json_decode($page['fields']), 'value', 'id');

            // Mandatory fields
            $pageTitle = $fields['hyper_title'];
            $pageCss = $fields['hyper_css'];
            $pageHtml = $fields['hyper_html'];
            $pageUrl = isset($fields['hyper_page_url']) ? $fields['hyper_page_url'] : null; // @TODO: Decide whether this is mandatory/optional

            // Optional fields
            $pageHook = isset($fields['hyper_page_hook_id']) ? $fields['hyper_page_hook_id'] : null;

            if ($pageHook === 'frontend:body') {
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
                HyperHooks::getInstance()->register('frontend:head', function () use ($pageTitle) {
                    return "<title>$pageTitle</title>";
                }, 1);
            }

            // Store the CSS in the head with higher priority
            HyperHooks::getInstance()->register('frontend:head', function () use ($pageCss) {
                return "<style>$pageCss</style>";
            }, 9);

            // Put the page's HTML content based on the hook set
            HyperHooks::getInstance()->register($pageHook, function () use ($pageHtml) {
                return $pageHtml;
            });

            log_message('debug', "Showing page: $pageTitle");
        }

        if (!$isPageExists) {
            // No matching page found—return a 404.
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        /* @TEST: End of register page hooks */

        return view('frontend');
    }
}
