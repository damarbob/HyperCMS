<?php

namespace PagingSystem\Controllers;

use App\Modules\Blueprints;
use App\Modules\ModuleController;
use InvalidArgumentException;
use Throwable;

/**
 * The models the page module works with. For each of its blueprints the screen shows the model that is chosen, the models
 * that fit and could be chosen, and a button that makes a model when none fits yet.
 */
class Settings extends ModuleController
{
    private const KEYS = ['page', 'asset', 'meta'];

    public function index()
    {
        $blueprints = $this->blueprints();
        $rows       = [];
        foreach (self::KEYS as $key) {
            $blueprint = $blueprints->find('PagingSystem', $key);
            $fitting   = $blueprints->fitting($blueprint);
            $current   = service('modules')->modelOf('PagingSystem', $key);
            $rows[]    = [
                'key'         => $key,
                'label'       => $blueprint->label,
                'description' => $blueprint->description,
                'fitting'     => $fitting,
                'current'     => $current,
                // A chosen model that was deleted, or changed so it no longer fits, is not a choice any more.
                'valid'       => $current !== null && in_array($current, array_column($fitting, 'id'), true),
            ];
        }

        return $this->page('PagingSystem\Views\settings', [
            'title'  => lang('PagingSystem.moduleName'),
            'crumbs' => [[lang('Admin.settings'), base_url('admin/settings')], [lang('PagingSystem.moduleName'), null]],
            'rows'   => $rows,
            'urls'   => ['save' => base_url('admin/settings/paging-system'), 'make' => base_url('admin/settings/paging-system/make/')],
        ]);
    }

    public function update()
    {
        $blueprints = $this->blueprints();
        $posted     = (array) $this->request->getPost('models');
        $changed    = 0;

        foreach (self::KEYS as $key) {
            $id = $posted[$key] ?? '';
            if ($id === '' || ! ctype_digit((string) $id)) {
                continue;
            }

            $fitting = array_column($blueprints->fitting($blueprints->find('PagingSystem', $key)), 'id');
            if (! in_array((int) $id, $fitting, true)) {
                return $this->leave(base_url('admin/settings/paging-system'), lang('PagingSystem.modelDoesNotFit'));
            }
            if (service('modules')->modelOf('PagingSystem', $key) !== (int) $id) {
                service('modules')->useModel('PagingSystem', $key, (int) $id);
                $changed++;
            }
        }

        return $this->leave(base_url('admin/settings/paging-system'), lang($changed > 0 ? 'PagingSystem.modelsSaved' : 'PagingSystem.nothingChanged'), 'ok');
    }

    public function make($key = '')
    {
        if (! in_array($key, self::KEYS, true)) {
            return $this->leave(base_url('admin/settings/paging-system'), lang('PagingSystem.noSuchModel'));
        }

        try {
            $blueprints = $this->blueprints();
            $blueprints->make('PagingSystem', $blueprints->find('PagingSystem', $key), (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            return $this->leave(base_url('admin/settings/paging-system'), $e->getMessage());
        } catch (Throwable $e) {
            // The model is saved and then registered with the engine, and a failure of the second part is finished later.
            log_message('error', 'The page module could not finish making a model. {message}', ['message' => $e->getMessage()]);

            return $this->leave(base_url('admin/settings/paging-system'), lang('PagingSystem.modelNotMade'));
        }

        return $this->leave(base_url('admin/settings/paging-system'), lang('PagingSystem.modelMade'), 'ok');
    }

    private function blueprints(): Blueprints
    {
        return new Blueprints(service('modelStore'), service('modules'));
    }
}
