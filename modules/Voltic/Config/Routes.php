<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('admin', ['namespace' => '\Voltic\Controllers'], static function ($routes) {
    $routes->get('voltic', 'Voltic');
    $routes->post('voltic/ask', 'Voltic::ask');
});
