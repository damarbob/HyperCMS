<?php

use App\Filters\ApiAuthFilter;
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

    $routes->group('', ['filter' => 'session'], static function ($routes) {

        $routes->addRedirect('/', 'admin/dashboard', 301);
        $routes->get('dashboard', 'Dashboard', ['as' => 'dashboard']);

        // Model
        $routes->group('model', ['filter' => 'model-user-group:model'], static function ($routes) {
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
            'filter' => 'group:superadmin',
        ]);
        $routes->group('models', ['filter' => 'group:superadmin'], static function ($routes) {
            $routes->post('delete', 'Models::delete');
            $routes->post('purge-deleted', 'Models::purgeDeleted');
            $routes->post('restore', 'Models::restore');
        });

        // Model data
        $routes->resource('model-data', [
            'controller' => 'ModelData',
            'websafe' => 1,
            'placeholder' => '(:num)',
            'only' => ['show'],
            'filter' => 'model-user-group:model',
        ]);
        $routes->post('model-data/clear-history/(:num)', 'ModelData::clearHistory/$1', ['filter' => 'model-user-group:model,3']);

        // Entries
        $routes->get('entries', 'Entries::index', ['filter' => 'group:superadmin,admin,developer']);

        // File Manager
        $routes->get('file-manager', 'FileManager', ['filter' => 'group-not:user']);

        // Profile
        $routes->resource('profile', [
            'websafe' => 1,
            'only' => ['index', 'update'],
        ]);

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
            $routes->get('models', 'ModelsSettings', ['filter' => 'group:superadmin']);
            $routes->post('update', 'Settings::update');
        });

        // Admin api
        $routes->group('api', ['namespace' => 'App\Controllers\Admin\API'], static function ($routes) {
            // $routes->resource('user', ['websafe' => 1]);

            // File manager
            $routes->group('file-manager', ['filter' => 'group-not:user'], static function ($routes) {
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
    });

    // Access token generation (development)
    /** @todo place in a new controller */
    if (ENVIRONMENT !== 'production') {
        $routes->group('access-token', ['filter' => 'tokens'], static function ($routes) {
            $routes->get('list', static function () {
                $tokens = auth()->user()->accessTokens();

                return service('response')->setJSON(['tokens' => $tokens]);
            });

            $routes->post('generate', static function () {
                $user = auth()->user();

                // A token may only hand on scopes it has itself, so a login token cannot mint a wider one.
                $asked  = service('request')->getVar('scopes');
                $scopes = $asked === null ? ($user->currentAccessToken()?->scopes ?? []) : array_values(array_filter((array) $asked, 'is_string'));

                foreach ($scopes as $scope) {
                    if ($user->tokenCant($scope)) {
                        return service('response')->setStatusCode(403)->setJSON(['message' => lang('Filter.apiNotAllowed')]);
                    }
                }

                // The API refuses a token without an expiry date, so this one gets a date like a login token does.
                $token = ApiAuthFilter::issueToken($user, (string) service('request')->getVar('name'), $scopes);

                return service('response')->setJSON(['token' => $token->raw_token, 'expires_at' => $token->expires->format(DATE_ATOM)]);
            });

            $routes->post('revoke', static function () {
                auth()->user()->revokeAccessToken(service('request')->getVar('name'));

                return service('response')->setJSON(['success' => true]);
            });

            $routes->post('revoke-token', static function () {
                auth()->user()->revokeAccessTokenBySecret(service('request')->getVar('token'));

                return service('response')->setJSON(['success' => true]);
            });

            $routes->post('revoke-all', static function () {
                auth()->user()->revokeAllAccessTokens();

                return service('response')->setJSON(['success' => true]);
            });
        });
    }
});

$routes->group('api', ['namespace' => 'App\Controllers\API\v1'], static function ($routes) {
    // $routes->resource('models', ['websafe' => 1]);

    $routes->group('v1', ['filter' => 'cors'], static function ($routes) {
        $routes->group('auth', static function ($routes) {
            $routes->post('login', 'Auth::login');
        });

        // Every route below needs a signed-in admin session or an access token.

        // Model definitions and their saved versions
        $routes->group('', ['filter' => 'api-auth:models.read'], static function ($routes) {
            $routes->post('models', 'Models');
            $routes->post('model-data', 'ModelData');
        });

        // Entries, for the token API
        $routes->group('', ['filter' => 'api-auth:entries.read'], static function ($routes) {
            $routes->resource('entries-resource', ['controller' => 'EntriesResource', 'only' => ['index', 'show']]);
        });
    });
});
