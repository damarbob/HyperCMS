<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home');

// Publicly available features
$routes->group('public', ['namespace' => 'App\Controllers\Public'], static function ($routes) {

    // File Server
    $routes->group('file-server', static function ($routes) {
        $routes->get('serve/(:segment)', 'FileServer::serve/$1');
    });
});

// Authentication routes
$routes->group('auth', ['namespace' => 'App\Controllers\Auth'], static function ($routes) {
    service('auth')->routes($routes, ['except' => ['login', 'register']]);
    $routes->get('login', 'LoginController::loginView', ['as' => 'login']);
    $routes->post('login', 'LoginController::loginAction');
    $routes->get('register', 'RegisterController::registerView', ['as' => 'register']);
    $routes->post('register', 'RegisterController::registerAction');
});

// Admin routes
$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->addRedirect('/', 'admin/dashboard', 301);
    $routes->get('dashboard', 'Dashboard');

    // Model
    $routes->get('model/(:num)', 'Model::index/$1');
    $routes->addRedirect('model', 'admin/entries', 301);

    // Models
    $routes->resource('models', [
        'websafe' => 1,
        'placeholder' => '(:num)',
        'only' => ['index', 'new', 'create', 'edit', 'update', 'delete'],
    ]);
    $routes->post('models/delete', 'Models::delete');
    $routes->post('models/purge-deleted', 'Models::purgeDeleted');
    $routes->post('models/restore', 'Models::restore');

    // Model data
    $routes->resource('model-data', [
        'controller' => 'ModelData',
        'websafe' => 1,
        'placeholder' => '(:num)',
        'only' => ['show'],
    ]);
    $routes->post('model-data/clear-history/(:num)', 'ModelData::clearHistory/$1');

    // Entries
    $routes->resource('entries', [
        'websafe' => 1,
        'placeholder' => '(:num)',
        'only' => ['index', 'new', 'create', 'edit', 'update', 'delete'],
    ]);
    $routes->post('entries/delete', 'Entries::delete');
    $routes->post('entries/purge-deleted', 'Entries::purgeDeleted');
    $routes->post('entries/restore', 'Entries::restore');

    // Entry data
    $routes->resource('entry-data', [
        'controller' => 'EntryData',
        'websafe' => 1,
        'placeholder' => '(:num)',
        'only' => ['show'],
    ]);
    $routes->post('entry-data/clear-history/(:num)', 'EntryData::clearHistory/$1');

    // File Manager
    $routes->get('file-manager', 'FileManager');
    $routes->resource('profile', [
        'websafe' => 1,
        'only' => ['index', 'update'],
    ]);

    // Settings
    $routes->resource('settings', [
        'websafe' => 1,
        'only' => ['index', 'update'],
    ]);
    $routes->group('settings', static function ($routes) {
        $routes->get('models', 'ModelsSettings');
    });

    // Admin api
    $routes->group('api', ['namespace' => 'App\Controllers\Admin\API'], static function ($routes) {
        // $routes->resource('user', ['websafe' => 1]);

        // File manager
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
    });

    // Access token generation (development)
    /** @todo Implement access tokens management */
    if (ENVIRONMENT !== 'production') {
        $routes->get('access-token/generate', static function () {
            $token = auth()->user()->generateAccessToken(service('request')->getVar('token_name'));

            return json_encode(['token' => $token->raw_token]);
        });
    }
});

$routes->group('api', ['namespace' => 'App\Controllers\API\v1'], static function ($routes) {
    // $routes->resource('models', ['websafe' => 1]);

    $routes->group('v1', ['filter' => 'cors'], static function ($routes) {
        $routes->post('models', 'Models');
        $routes->post('model-data', 'ModelData');
        $routes->post('entry-data', 'EntryData');
        $routes->post('entries', 'Entries');
        $routes->post('model', 'Model');
    });
});
