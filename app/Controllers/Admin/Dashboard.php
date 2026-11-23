<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Fields\FieldTypes;
use App\Libraries\AllEntriesPage;

/**
 * The first page after signing in. It shows what was edited last, in the models the person may open, and the models
 * themselves with their entry counts, so a person can go straight to the work. It reads only what the lists read, so it
 * shows nothing the person could not open from the menu.
 */
class Dashboard extends AdminController
{
    /** How many recently edited entries the page shows. */
    private const RECENT = 8;

    public function index(): string
    {
        $recent = (new AllEntriesPage(FieldTypes::core()))->build(['sort' => 'date_modified', 'dir' => 'desc', 'per' => 10], 10);

        // The page is not a list to sort, so the headings are plain text.
        $columns = array_map(static fn (array $column): array => array_diff_key($column, ['href' => 1, 'dir' => 1]), $recent['columns']);

        $counts = service('entryStore')->countsByModel();
        $models = [];
        foreach ($recent['models'] as $id => $model) {
            $models[] = [
                'id'     => $id,
                'name'   => (string) $model['name'],
                'count'  => (int) ($counts[$id] ?? 0),
                'list'   => base_url('admin/model/' . $id),
                'new'    => can('entries.create', $id) ? base_url('admin/model/' . $id . '/new') : null,
            ];
        }
        usort($models, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        $this->data = array_merge($this->data, [
            'title'    => lang('Admin.dashboard'),
            'username' => (string) auth()->user()->username,
            'columns'  => $columns,
            'rows'     => array_slice($recent['rows'], 0, self::RECENT),
            'models'   => $models,
            'canBuild' => can('models.manage'),
        ]);

        return view('admin/dashboard', $this->data);
    }
}
