<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('', ['namespace' => '\PagingSystem\Controllers'], static function ($routes) {
    $routes->get('/', 'Frontend');

    // @IMPORTANT: Ensure restricted routes are updated in the regex negative lookahead paths list to prevent them from being treated as dynamic pages.
    $routes->get(ENVIRONMENT === 'production' ? '^(?!test|public|auth|api|admin)(.*)$' : '^(?!test|public|auth|api|admin|.hyper-dev)(.*)$', 'Frontend');
});

$routes->group('admin', ['namespace' => '\PagingSystem\Controllers\Admin'], static function ($routes) {
    $routes->get('editor', 'Editor');

    $routes->group('ps', static function ($routes) {
        $routes->group('entries', ['filter' => 'model-user-group:entries'], static function ($routes) {
            $routes->get('(:num)/new', 'Entries::new/$1');
        });

        $routes->group('api', ['namespace' => '\PagingSystem\Controllers\Admin\API'], static function ($routes) {
            $routes->group('assets', ['filter' => 'group-not:user'], static function ($routes) {
                $routes->post('upload', 'Assets::assetsUpload');
            });
        });
    });

    $routes->group('settings', static function ($routes) {
        $routes->get('paging-system', 'Settings');
        $routes->post('paging-system/update', 'Settings::update');
    });
});
