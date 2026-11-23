<?php

namespace DataComparison;

use App\Modules\MenuEntry;
use App\Modules\Module as BaseModule;
use App\Modules\ModuleRoutes;
use App\Modules\SettingsScreen;

/**
 * A table that puts entries side by side. A column is an entry, from this site or from another source, and a row is a field,
 * a field of the entry's model, one of the person's own, or a formula over other cells. It uses the ability `entries.browse`
 * of core, so the roles that may list entries may use it, and a person writes back only to entries of models they may update.
 */
final class Module extends BaseModule
{
    public function title(): string
    {
        return lang('Dc.moduleName');
    }

    public function menu(): array
    {
        return [new MenuEntry('data-comparison', lang('Dc.moduleName'), base_url('admin/data-comparison'), 'table', 'entries.browse', lang('Admin.data'), lang('Dc.moduleDescription'))];
    }

    public function settings(): array
    {
        return [new SettingsScreen('data-comparison', lang('Dc.moduleName'), base_url('admin/settings/data-comparison'), 'entries.browse')];
    }

    public function routes(ModuleRoutes $routes): void
    {
        $routes->admin('entries.browse', static function ($routes): void {
            $routes->get('data-comparison', 'DataComparison::index');
            $routes->post('data-comparison/entries', 'DataComparison::entries');
            $routes->post('data-comparison/data', 'Data::index');
            $routes->post('data-comparison/update', 'DataComparison::update');
            $routes->post('data-comparison/states', 'DataComparison::saveStates');
            $routes->post('data-comparison/last', 'DataComparison::saveLast');

            $routes->get('settings/data-comparison', 'Settings::index');
            $routes->post('settings/data-comparison', 'Settings::update');
        });

        // The address that a data source of an earlier version named, for a script or a token that still calls it.
        $routes->site(static function ($routes): void {
            $routes->group('api/v1', ['filter' => 'api-auth:entries.read'], static function ($routes): void {
                $routes->post('data-comparison/data', 'Data::index');
                $routes->post('data-comparison/entries', 'Data::index');
            });
        });
    }
}
