<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('', ['namespace' => '\PagingSystem\Controllers'], static function ($routes) {
    $routes->get('/', 'Frontend');

    // @IMPORTANT: Ensure restricted routes are updated in the regex negative lookahead paths list to prevent them from being treated as dynamic pages.
    $routes->get(ENVIRONMENT === 'production' ? '^(?!test|public|auth|api|admin|media/)(.*)$' : '^(?!test|public|auth|api|admin|media/|.hyper-dev)(.*)$', 'Frontend');
});

$routes->group('admin', ['namespace' => '\PagingSystem\Controllers\Admin'], static function ($routes) {
    // The page editor changes the pages of the site, so it is for the people who manage pages.
    $routes->group('', ['filter' => 'can:pages.manage'], static function ($routes) {
        $routes->get('editor', 'Editor');
    });

    $routes->group('ps', static function ($routes) {
        // The model id is the fourth segment, which is index 3 of the filter.
        $routes->group('entries', ['filter' => 'can:entries.create,3'], static function ($routes) {
            $routes->get('(:num)/new', 'Entries::new/$1');
        });

        $routes->group('api', ['namespace' => '\PagingSystem\Controllers\Admin\API'], static function ($routes) {
            $routes->group('assets', ['filter' => 'can:media.manage'], static function ($routes) {
                $routes->post('upload', 'Assets::assetsUpload');
            });
        });
    });

    $routes->group('settings', ['filter' => 'can:pages.manage'], static function ($routes) {
        $routes->get('paging-system', 'Settings');
        $routes->post('paging-system/update', 'Settings::update');
    });
});
