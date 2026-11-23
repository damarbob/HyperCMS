<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home');

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

    $routes->group('', ['filter' => 'session'], static function ($routes) {

        $routes->addRedirect('/', 'admin/dashboard', 301);
        $routes->get('dashboard', 'Dashboard', ['as' => 'dashboard']);

        // Model
        $routes->group('model', ['filter' => 'can:entries.read,2'], static function ($routes) {
            $routes->group('(:num)', static function ($routes) {
                $routes->get('/', 'Model::index/$1');
                $routes->get('export', 'Model::export/$1');
                $routes->get('new', 'Entries::new/$1');
                $routes->post('/', 'Entries::create/$1');
                $routes->post('trash', 'Entries::trash/$1');
                $routes->post('restore', 'Entries::restore/$1');
                $routes->post('purge', 'Entries::purge/$1');
                $routes->get('(:num)/edit', 'Entries::edit/$1/$2');
                $routes->post('(:num)', 'Entries::update/$1/$2');
                $routes->post('(:num)/trash', 'Entries::trashOne/$1/$2');
                $routes->get('(:num)/history', 'Entries::history/$1/$2');
                $routes->post('(:num)/history/clear', 'Entries::clearHistory/$1/$2');
                $routes->post('(:num)/versions/(:num)/restore', 'Entries::restoreVersion/$1/$2/$3');
            });
            $routes->addRedirect('/', 'admin/entries', 301);
        });

        // Models
        $routes->resource('models', [
            'websafe' => 1,
            'placeholder' => '(:num)',
            'only' => ['index', 'new', 'create', 'edit', 'update', 'delete'],
            'filter' => 'can:models.manage',
        ]);
        $routes->group('models', ['filter' => 'can:models.manage'], static function ($routes) {
            $routes->post('delete', 'Models::delete');
            $routes->post('check', 'Models::check');
            $routes->post('purge-deleted', 'Models::purgeDeleted');
            $routes->post('restore', 'Models::restore');
        });

        // Model data
        $routes->resource('model-data', [
            'controller' => 'ModelData',
            'websafe' => 1,
            'placeholder' => '(:num)',
            'only' => ['show'],
            'filter' => 'can:models.manage',
        ]);
        // A restore rewrites the definition of the model, so these are for the people who manage models.
        $routes->post('model-data/(:num)/clear', 'ModelData::clearHistory/$1', ['filter' => 'can:models.manage']);
        $routes->post('model-data/(:num)/versions/(:num)/restore', 'ModelData::restoreVersion/$1/$2', ['filter' => 'can:models.manage']);

        // The access tokens of the signed-in person.
        $routes->group('tokens', ['filter' => 'can:tokens.manage'], static function ($routes) {
            $routes->get('/', 'Tokens::index');
            $routes->post('/', 'Tokens::create');
            $routes->post('revoke-all', 'Tokens::revokeAll');
            $routes->post('(:num)/revoke', 'Tokens::revoke/$1');
        });

        // Roles and abilities. Giving a role what the person has themselves is checked in the controller.
        $routes->group('roles', ['filter' => 'can:roles.manage'], static function ($routes) {
            $routes->get('/', 'Roles::index');
            $routes->get('new', 'Roles::new');
            $routes->post('/', 'Roles::create');
            $routes->get('(:segment)/edit', 'Roles::edit/$1');
            $routes->post('(:segment)/delete', 'Roles::delete/$1');
            $routes->post('(:segment)', 'Roles::update/$1');
        });
        $routes->group('abilities', ['filter' => 'can:roles.manage'], static function ($routes) {
            $routes->get('/', 'Abilities::index');
            $routes->post('/', 'Abilities::create');
            $routes->post('(:segment)/delete', 'Abilities::delete/$1');
        });

        // The lookup of the entry pickers. The controller checks the right to read the entries of that model.
        $routes->get('picker/(:num)', 'Picker::entries/$1');

        // Entries
        $routes->get('entries', 'Entries::index', ['filter' => 'can:entries.browse']);

        // Profile
        $routes->get('profile', 'Profile::index');
        $routes->post('profile', 'Profile::update');

        // Colour scheme of the signed-in user
        $routes->post('theme', 'Theme::save');

        // Every part of the design system, for the people who build screens
        if (ENVIRONMENT !== 'production') {
            $routes->get('design', 'Design::index');
        }

        // Settings
        $routes->resource('settings', [
            'websafe' => 1,
            'only' => ['index'],
        ]);
        $routes->group('settings', static function ($routes) {
            $routes->get('models', 'ModelsSettings', ['filter' => 'can:models.manage']);
            $routes->post('update', 'Settings::update');
        });
    });

});

$routes->group('api', ['namespace' => 'App\Controllers\API\v1'], static function ($routes) {
    // $routes->resource('models', ['websafe' => 1]);

    $routes->group('v1', ['filter' => 'cors'], static function ($routes) {
        $routes->group('auth', static function ($routes) {
            $routes->post('login', 'Auth::login');
        });

        // Every route below needs a signed-in admin session or an access token.

        // Model definitions
        $routes->group('', ['filter' => 'api-auth:models.read'], static function ($routes) {
            $routes->post('models', 'Models');
        });

        // Entries, for the token API
        $routes->group('', ['filter' => 'api-auth:entries.read'], static function ($routes) {
            $routes->resource('entries-resource', ['controller' => 'EntriesResource', 'only' => ['index', 'show']]);
        });
    });
});

// The routes the modules that follow the contract ask for.
service('modules')->routes($routes);
