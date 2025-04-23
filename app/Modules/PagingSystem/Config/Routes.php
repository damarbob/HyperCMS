<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', '\Modules\PagingSystem\Controllers\Frontend');

// @IMPORTANT: Ensure restricted routes are updated in the regex negative lookahead paths list to prevent them from being treated as dynamic pages.
$routes->get('^(?!test|auth|api|admin)(.*)$', '\Modules\PagingSystem\Controllers\Frontend');

$routes->group('admin', ['namespace' => '\Modules\PagingSystem\Controllers\Admin'], static function ($routes) {
    $routes->get('editor', 'Editor');

    $routes->group('settings', ['namespace' => '\Modules\PagingSystem\Controllers\Admin'], static function ($routes) {
        $routes->get('paging-system', 'Settings');
        $routes->post('paging-system/update', 'Settings::update');
    });
});
