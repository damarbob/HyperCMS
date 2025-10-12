<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('admin', ['namespace' => '\DataComparison\Controllers'], static function ($routes) {
    $routes->get('data-comparison', 'DataComparison');
    $routes->post('data-comparison/update', 'DataComparison::update');

    $routes->group('settings', static function ($routes) {
        $routes->get('data-comparison', 'Settings');
        $routes->post('data-comparison/update', 'Settings::update');
        $routes->get('data-comparison/load-state', 'Settings::loadState');
        $routes->post('data-comparison/save-state', 'Settings::saveState');
        $routes->get('data-comparison/get-last-used-state', 'Settings::getLastUsedState');
        $routes->post('data-comparison/save-last-used-state', 'Settings::saveLastUsedState');
    });
});
$routes->group('api', static function ($routes) {
    $routes->group('v1', ['namespace' => '\DataComparison\Controllers\API\v1'], static function ($routes) {
        $routes->post('data-comparison/data', 'Data');
        $routes->post('data-comparison/entries', 'Data');
    });
});
