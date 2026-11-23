<?php

namespace App\Modules;

use App\Fields\FieldType;

/**
 * What a module is to core. A module is a folder under `modules/` that holds a class named `Module` in its own namespace,
 * `UserManagement\Module` for the folder `modules/UserManagement`, which extends this one and overrides what it offers.
 *
 * Core calls these methods and nothing else of the module, so a module needs no hook names and no import from
 * `App\Controllers`. Everything a method returns is plain data or a small class of `App\Modules`.
 */
abstract class Module
{
    /**
     * The name of the module, which is the first part of its namespace and the name of its folder.
     */
    final public function name(): string
    {
        return explode('\\', static::class)[0];
    }

    /**
     * What a person sees as the name of the module, in their language.
     */
    abstract public function title(): string;

    /**
     * The versions of core the module works with, as a comparison such as `>=0.2.0`, or `*` for any. A module that does not fit is not
     * loaded, and the admin says why.
     */
    public function requires(): string
    {
        return '*';
    }

    /**
     * The abilities the module adds. A role can be given them on the Roles screen.
     *
     * @return list<Ability>
     */
    public function abilities(): array
    {
        return [];
    }

    /**
     * The items the module adds to the admin menu. An item with an ability is shown only to roles that have it.
     *
     * @return list<MenuEntry>
     */
    public function menu(): array
    {
        return [];
    }

    /**
     * The routes of the module. Pages of the admin go through `$routes->admin()`, which adds the session check and the
     * ability check, and pages for visitors go through `$routes->site()`.
     */
    public function routes(ModuleRoutes $routes): void
    {
    }

    /**
     * The screens the module adds to the settings.
     *
     * @return list<SettingsScreen>
     */
    public function settings(): array
    {
        return [];
    }

    /**
     * The field types the module adds. A model can use them as soon as the module is loaded.
     *
     * @return list<FieldType>
     */
    public function fieldTypes(): array
    {
        return [];
    }

    /**
     * The models the module needs, such as the page model of a page module. The admin offers to make each one, or to pick
     * an existing model that fits, and keeps the choice in the settings.
     *
     * @return list<Blueprint>
     */
    public function blueprints(): array
    {
        return [];
    }

    /**
     * Put content into the named places of core pages, such as the toolbar of the entry form.
     */
    public function slots(Slots $slots): void
    {
    }
}
