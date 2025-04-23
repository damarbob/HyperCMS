<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home');

$routes->group('auth', ['namespace' => 'App\Controllers\Auth'], static function ($routes) {
    service('auth')->routes($routes, ['except' => ['login', 'register']]);
    $routes->get('login', 'LoginController::loginView', ['as' => 'login']);
    $routes->post('login', 'LoginController::loginAction');
    $routes->get('register', 'RegisterController::registerView', ['as' => 'register']);
    $routes->post('register', 'RegisterController::registerAction');
});

$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->addRedirect('/', 'admin/dashboard', 301);
    $routes->get('dashboard', 'Dashboard');
    $routes->get('model', 'Model');
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
    $routes->get('file-manager', 'FileManager');
    $routes->resource('settings', [
        'websafe' => 1,
        'only' => ['index', 'update'],
    ]);
    $routes->resource('profile', [
        'websafe' => 1,
        'only' => ['index', 'update'],
    ]);
});

$routes->group('app', ['namespace' => 'App\Controllers\App'], static function ($routes) {
    $routes->get('/', 'App');
});

$routes->group('api', ['namespace' => 'App\Controllers\API\v1'], static function ($routes) {
    $routes->resource('user', ['websafe' => 1]);
    $routes->resource('models', ['websafe' => 1]);

    $routes->group('file-manager', static function ($routes) {
        $routes->get('view-file/(:any)', 'FileManager::viewFile/$1');
        $routes->get('list-files/(:any)', 'FileManager::listFiles/$1');
        $routes->get('list-files', 'FileManager::listFiles');
        $routes->post('save-file', 'FileManager::saveFile');
        $routes->post('set-clipboard', 'FileManager::setClipboard');
        $routes->post('paste', 'FileManager::paste');
        $routes->post('rename', 'FileManager::rename');
        $routes->post('create-file', 'FileManager::createFile');
        $routes->post('create-folder', 'FileManager::createFolder');
        $routes->post('delete-files', 'FileManager::deleteFiles');
        $routes->post('upload', 'FileManager::upload');
        $routes->post('compress', 'FileManager::compress');
        $routes->post('extract', 'FileManager::extract');
        $routes->post('bulk-action', 'FileManager::bulkAction');
        $routes->get('download/(:any)', 'FileManager::download/$1');
    });

    $routes->group('file-server', static function ($routes) {
        $routes->get('serve/(:segment)', 'FileServer::serve/$1');
    });

    // @TODO: Finalize this
    $routes->group('test', ['filter' => 'cors'], static function ($routes) {
        $routes->post('models/dt', 'Models');
        $routes->post('entries/dt', 'Entries');
        $routes->post('model/dt', 'Model');
        $routes->post('entries/create/(:num)', 'Entries::create/$1');
        $routes->post('entries/save/(:segment)', 'Entries::save/$1');
    });
});
