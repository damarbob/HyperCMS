<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('', ['namespace' => '\PagingSystem\Controllers'], static function ($routes) {
    $routes->get('/', 'Frontend');

    // @IMPORTANT: Ensure restricted routes are updated in the regex negative lookahead paths list to prevent them from being treated as dynamic pages.
    $routes->get('^(?!test|public|auth|api|admin)(.*)$', 'Frontend');
});

$routes->group('admin', ['namespace' => '\PagingSystem\Controllers\Admin'], static function ($routes) {
    $routes->get('editor', 'Editor');

    $routes->group('settings', ['namespace' => '\PagingSystem\Controllers\Admin'], static function ($routes) {
        $routes->get('paging-system', 'Settings');
        $routes->post('paging-system/update', 'Settings::update');
    });
});
