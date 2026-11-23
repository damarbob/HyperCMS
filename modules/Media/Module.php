<?php

namespace Media;

use App\Modules\MenuEntry;
use App\Modules\Module as BaseModule;
use App\Modules\ModuleRoutes;

/**
 * The media library. It keeps the files of the site in one place outside the public folder, hands each one out at a link
 * that never changes (`/media/{id}/{name}`), and lets a person pick files for a field, the rich text editor and the page
 * editor. It uses the ability `media.manage` of core, and the storage itself is the `files` service of core, so a field
 * of type `file` works the same wherever it is shown.
 */
final class Module extends BaseModule
{
    public function title(): string
    {
        return lang('Media.moduleName');
    }

    public function menu(): array
    {
        return [new MenuEntry('media', lang('Media.moduleName'), base_url('admin/media'), 'media', 'media.manage', '', lang('Media.moduleDescription'))];
    }

    public function routes(ModuleRoutes $routes): void
    {
        $routes->admin('media.manage', static function ($routes): void {
            $routes->get('media', 'Library::index');
            $routes->get('media/picker', 'Library::picker');
            $routes->get('media/frame', 'Library::frame');
            $routes->post('media/upload', 'Library::upload');
            $routes->post('media/folders', 'Library::createFolder');
            $routes->post('media/folders/rename', 'Library::renameFolder');
            $routes->post('media/folders/delete', 'Library::deleteFolder');
            $routes->post('media/rename', 'Library::rename');
            $routes->post('media/move', 'Library::move');
            $routes->post('media/delete', 'Library::delete');
        });

        $routes->site(static function ($routes): void {
            $routes->match(['GET', 'HEAD'], 'media/(:num)', 'Serve::file/$1');
            $routes->match(['GET', 'HEAD'], 'media/(:num)/(:any)', 'Serve::file/$1/$2');
        });
    }
}
