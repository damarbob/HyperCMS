<?php

namespace PagingSystem\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use PagingSystem\Config\PagingSystem as PagingConfig;
use PagingSystem\Libraries\PageRenderer;
use PagingSystem\Libraries\Pages;

/**
 * Answers an address that no other route took, with the page that has it, or with the 404 page of the site. The page is kept
 * in the cache under the number that changes whenever anything is saved, so a saved change shows at once and a page that
 * did not change is made once.
 */
class Frontend extends Controller
{
    private const LIFETIME = 86400;

    public function index()
    {
        $config = config(PagingConfig::class);
        $path   = trim((string) service('request')->getPath(), '/');
        $home   = $path === '';
        $path   = $home ? $config->homePath : $path;

        $pages = new Pages(service('modules'), service('entryStore'), new \App\Data\WriteVersions());
        $query = (array) $this->request->getGet();

        $key   = 'ps_page_' . sha1($path) . '_' . $pages->version();
        $found = cache($key);
        if (is_array($found) && ($found['dynamic'] ?? false)) {
            $key  .= '_' . sha1(http_build_query($query));
            $found = cache($key);
        }

        if (! is_array($found)) {
            $found = (new PageRenderer($pages))->render($path, $query);
            if ($found !== null) {
                // A page that reads the query string is kept once per query, and its plain key says so.
                if ($found['dynamic']) {
                    cache()->save('ps_page_' . sha1($path) . '_' . $pages->version(), ['dynamic' => true, 'html' => ''], self::LIFETIME);
                    $key = 'ps_page_' . sha1($path) . '_' . $pages->version() . '_' . sha1(http_build_query($query));
                }
                cache()->save($key, $found, self::LIFETIME);
            }
        }

        if (is_array($found) && isset($found['html']) && $found['html'] !== '') {
            return $this->response->setBody($found['html']);
        }

        if ($home) {
            // No page of the site is made for `/` yet, so the welcome page of the application is shown.
            return view('app');
        }

        throw PageNotFoundException::forPageNotFound();
    }
}
