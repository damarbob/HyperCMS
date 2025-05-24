<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('admin', ['namespace' => 'UserManagement\Controllers', 'filter' => 'group:superadmin'], static function ($routes) {
    $routes->resource('users', [
        'websafe' => 1,
        'placeholder' => '(:num)',
        'only' => ['index', 'show', 'delete'],
    ]);
    $routes->get('users/get-users', 'Users::getUsers');
    $routes->post('users/save', 'Users::save');
});
