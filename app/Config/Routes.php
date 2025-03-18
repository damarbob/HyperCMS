<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
// $routes->get('/', 'Home::index');
$routes->addRedirect('/', 'admin', 301);

service('auth')->routes($routes);

$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->addRedirect('/', 'admin/dashboard', 301);
    $routes->get('dashboard', 'Dashboard');
    $routes->get('editor', 'Editor');
    $routes->resource('models', [
        'websafe' => 1,
        'placeholder' => '(:num)',
        'only' => ['index', 'new', 'create', 'edit', 'update', 'delete'],
    ]);
    $routes->resource('entries', [
        'websafe' => 1,
        'placeholder' => '(:num)',
        'only' => ['index', 'new', 'create', 'edit', 'update', 'delete'],
    ]);
    $routes->get('settings', 'Settings');
});

$routes->group('app', ['namespace' => 'App\Controllers\App'], static function ($routes) {
    $routes->get('/', 'App');
});

$routes->group('api', ['namespace' => 'App\Controllers\API\v1'], static function ($routes) {
    $routes->resource('user', ['websafe' => 1]);
    $routes->resource('models', ['websafe' => 1]);

    // @TODO: Finalize this
    $routes->group('test', static function ($routes) {
        $routes->post('models/dt', 'Models');
        $routes->post('entries/dt', 'Entries');
        $routes->post('entries/create/(:num)', 'Entries::create/$1');
        $routes->post('entries/save/(:segment)', 'Entries::save/$1');
    });
});
