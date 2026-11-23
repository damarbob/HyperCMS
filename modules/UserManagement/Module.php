<?php

namespace UserManagement;

use App\Modules\MenuEntry;
use App\Modules\Module as BaseModule;
use App\Modules\ModuleRoutes;

/**
 * The people who can sign in: a list, and a screen to make, change, ban and remove an account and to choose its roles.
 * It uses the ability `users.manage` of core, and it is the first module written to the module contract.
 */
final class Module extends BaseModule
{
    public function title(): string
    {
        return lang('UserManagement.moduleName');
    }

    public function menu(): array
    {
        return [new MenuEntry('user-management', lang('UserManagement.moduleName'), base_url('admin/users'), 'users', 'users.manage', '', lang('UserManagement.moduleDescription'))];
    }

    public function routes(ModuleRoutes $routes): void
    {
        $routes->admin('users.manage', static function ($routes): void {
            $routes->get('users', 'Users::index');
            $routes->get('users/new', 'Users::new');
            $routes->post('users', 'Users::create');
            $routes->get('users/(:num)/edit', 'Users::edit/$1');
            $routes->post('users/(:num)', 'Users::update/$1');
            $routes->post('users/(:num)/ban', 'Users::ban/$1');
            $routes->post('users/(:num)/unban', 'Users::unban/$1');
            $routes->post('users/(:num)/delete', 'Users::delete/$1');
        });
    }
}
