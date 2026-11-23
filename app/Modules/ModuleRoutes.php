<?php

namespace App\Modules;

use CodeIgniter\Router\RouteCollection;

/**
 * The routes a module may make, which are only two kinds: pages of the admin, and pages for visitors. The controllers
 * are looked up in the `Controllers` folder of the module's own namespace, so a module cannot route to a controller of
 * core or of another module.
 */
final class ModuleRoutes
{
    public function __construct(private readonly RouteCollection $routes, private readonly string $module)
    {
    }

    /**
     * Pages under `admin/`. Someone who is not signed in goes to the login page, and when an ability is given, someone
     * whose roles lack it is turned away.
     *
     * @param callable(RouteCollection): void $define
     */
    public function admin(?string $ability, callable $define): void
    {
        $this->routes->group('admin', ['namespace' => $this->module . '\\Controllers', 'filter' => $ability === null ? 'session' : 'can:' . $ability], $define);
    }

    /**
     * Pages for visitors, with no sign in.
     *
     * @param callable(RouteCollection): void $define
     */
    public function site(callable $define): void
    {
        $this->routes->group('', ['namespace' => $this->module . '\\Controllers'], $define);
    }
}
