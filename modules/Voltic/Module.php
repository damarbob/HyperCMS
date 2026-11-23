<?php

namespace Voltic;

use App\Modules\MenuEntry;
use App\Modules\Module as BaseModule;
use App\Modules\ModuleRoutes;

/**
 * An assistant that answers in a chat and can propose actions: make a model, make an entry, show an entry. It sends the
 * models and entries that the signed-in person may read to the AI service of the site, and never anything else. An action is
 * only proposed in the chat. It runs when the person confirms it, and then under their own abilities, as if they had made the
 * model or the entry on the screens of the admin.
 *
 * It uses the ability `entries.browse` of core to open the chat.
 */
final class Module extends BaseModule
{
    public function title(): string
    {
        return lang('Voltic.moduleName');
    }

    public function menu(): array
    {
        return [new MenuEntry('voltic', lang('Voltic.moduleName'), base_url('admin/voltic'), 'bolt', 'entries.browse', lang('Admin.ai'), lang('Voltic.moduleDescription'))];
    }

    public function routes(ModuleRoutes $routes): void
    {
        $routes->admin('entries.browse', static function ($routes): void {
            $routes->get('voltic', 'Voltic::index');
            $routes->post('voltic/ask', 'Voltic::ask');
            $routes->post('voltic/run', 'Voltic::run');
        });
    }
}
