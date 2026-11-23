<?php

namespace PagingSystem;

use App\Modules\Blueprint;
use App\Modules\Module as BaseModule;
use App\Modules\ModuleRoutes;
use App\Modules\SettingsScreen;
use App\Modules\Slots;
use App\Modules\Slots\EntryFormToolbar;
use PagingSystem\Config\PagingSystem as PagingConfig;

/**
 * Pages of the site, made in a visual editor. It uses the ability `pages.manage` of core and three models that the
 * settings of the module name: the page model, the model of the scripts and styles every page loads, and the model of the
 * meta tags every page carries. Every address that no other route takes goes to the page that has it.
 */
final class Module extends BaseModule
{
    public function title(): string
    {
        return lang('PagingSystem.moduleName');
    }

    public function settings(): array
    {
        return [new SettingsScreen('paging-system', lang('PagingSystem.moduleName'), base_url('admin/settings/paging-system'), 'pages.manage')];
    }

    public function routes(ModuleRoutes $routes): void
    {
        $routes->admin('pages.manage', static function ($routes): void {
            $routes->get('editor', 'Editor::index');
            $routes->post('editor/(:num)/save', 'Editor::save/$1');
            $routes->get('settings/paging-system', 'Settings::index');
            $routes->post('settings/paging-system', 'Settings::update');
            $routes->post('settings/paging-system/make/(:segment)', 'Settings::make/$1');
        });

        $routes->admin('media.manage', static function ($routes): void {
            $routes->post('ps/api/assets/upload', 'Assets::assetsUpload');
        });

        // Last, so that the routes of the modules above are tried first. The pattern leaves out the addresses of the
        // application, which the configuration lists.
        $routes->site(static function ($routes): void {
            $routes->get('/', 'Frontend::index');
            $routes->get(config(PagingConfig::class)->routePattern(), 'Frontend::index');
        });
    }

    public function slots(Slots $slots): void
    {
        $slots->on(Slots::ENTRY_FORM_TOOLBAR, static function (EntryFormToolbar $toolbar): void {
            $page = service('modules')->modelOf('PagingSystem', 'page');
            if ($page !== null && $toolbar->modelId === $page && ! $toolbar->isNew() && can('pages.manage')) {
                $toolbar->link(lang('PagingSystem.openWithEditor'), base_url('admin/editor?entry_id=' . $toolbar->entryId), 'edit');
            }
        });
    }

    public function blueprints(): array
    {
        $code = static fn (string $id, string $label, string $language, string $help): array => ['id' => $id, 'label' => $label, 'type' => 'code', 'language' => $language, 'list' => false, 'help' => $help];

        return [
            new Blueprint(
                'page',
                lang('PagingSystem.page'),
                lang('PagingSystem.pageModelText'),
                [
                    ['id' => 'hyper_title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'help' => 'Used for display purposes. When the attachment is Main Frontend, this title is the title of the page.'],
                    ['id' => 'hyper_page_hook_id', 'label' => 'Part attachment', 'type' => 'hook', 'group' => 'Frontend', 'filterable' => true, 'help' => 'The attachment says where the part is shown. To make a page, choose Main Frontend.'],
                    ['id' => 'hyper_page_url', 'label' => 'Url', 'type' => 'text', 'filterable' => true, 'help' => 'For the Main Frontend attachment, the address of the page without a slash in front, such as products.'],
                    $code('hyper_html', 'HTML', 'html', 'The HTML of the part. The editor fills it in.'),
                    $code('hyper_css', 'CSS', 'css', 'The CSS of the part. The editor fills it in.'),
                    $code('hyper_component_elements', 'Component elements', 'json', 'The editor fills this in.'),
                    $code('hyper_page_project_data', 'Project data', 'json', 'The editor fills this in.'),
                ],
                'hyper_title',
                'doc',
            ),
            new Blueprint(
                'asset',
                lang('PagingSystem.assets'),
                lang('PagingSystem.assetModelText'),
                [
                    ['id' => 'asset_label', 'label' => 'Label', 'type' => 'text', 'help' => 'A name for the asset. It is only shown here.'],
                    ['id' => 'asset_url', 'label' => 'File', 'type' => 'file', 'required' => true, 'help' => 'The file from the media library, or a link when the field is text.'],
                    ['id' => 'asset_type', 'label' => 'Type', 'type' => 'choice', 'widget' => 'select', 'choices' => [['value' => 'script', 'label' => 'Script'], ['value' => 'style', 'label' => 'Style']], 'default' => 'script', 'required' => true, 'help' => 'Script for JavaScript, Style for CSS.'],
                    ['id' => 'asset_placement', 'label' => 'Placement', 'type' => 'choice', 'widget' => 'select', 'choices' => [['value' => 'head', 'label' => 'Head'], ['value' => 'body', 'label' => 'Body']], 'default' => 'head', 'required' => true, 'help' => 'Where in the page the asset is loaded.'],
                ],
                'asset_label',
                'doc',
                ['asset_url' => ['text']],
            ),
            new Blueprint(
                'meta',
                lang('PagingSystem.meta'),
                lang('PagingSystem.metaModelText'),
                [
                    ['id' => 'meta_name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'help' => 'The name of the meta tag, such as description.'],
                    ['id' => 'meta_content', 'label' => 'Content', 'type' => 'longtext', 'help' => 'The content of the meta tag.'],
                ],
                'meta_name',
                'doc',
                ['meta_content' => ['text', 'richtext']],
            ),
        ];
    }
}
