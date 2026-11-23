<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Data\Entries\FilterableFields;
use App\Fields\FieldTypes;
use App\Libraries\AllEntriesPage;
use App\Libraries\DashboardAttention;
use App\Modules\Slots;
use App\Modules\Slots\DashboardPanels;
use Throwable;

/**
 * The first page after signing in. It shows what needs a look (only when something does), the entries this person edited last
 * and what others edited, and the models with their entry counts, so a person can go straight to the work. Modules add
 * panels of their own through a slot. It reads only what the lists read, so it shows nothing the person could not open from
 * the menu.
 */
class Dashboard extends AdminController
{
    /** How many recently edited entries each table shows. */
    private const MINE = 5;

    private const OTHERS = 8;

    public function index(): string
    {
        $types  = FieldTypes::core();
        $page   = new AllEntriesPage($types);
        $userId = (int) auth()->id();

        $mine   = $page->build(['sort' => 'date_modified', 'dir' => 'desc', 'per' => 10], 10, ['only' => $userId]);
        $others = $page->build(['sort' => 'date_modified', 'dir' => 'desc', 'per' => 10], 10, ['except' => $userId]);

        // The page is not a list to sort, so the headings are plain text.
        $columns = array_map(static fn (array $column): array => array_diff_key($column, ['href' => 1, 'dir' => 1]), $others['columns']);

        $counts = service('entryStore')->countsByModel();
        $models = [];
        foreach ($others['models'] as $id => $model) {
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
            'title'     => lang('Admin.dashboard'),
            'username'  => (string) auth()->user()->username,
            'columns'   => $columns,
            'mine'      => array_slice($mine['rows'], 0, self::MINE),
            'others'    => array_slice($others['rows'], 0, self::OTHERS),
            'models'    => $models,
            'canBuild'  => can('models.manage'),
            'attention' => $this->attention(),
            'panels'    => $this->panels(),
        ]);

        return view('admin/dashboard', $this->data);
    }

    /**
     * What to point out. A failure to find out, such as StarDust not answering, shows nothing instead of breaking the page.
     *
     * @return list<array{tone: string, text: string, links: list<array{label: string, href: string}>}>
     */
    private function attention(): array
    {
        $manageModels   = can('models.manage');
        $manageSettings = can('settings.manage');
        if (! $manageModels && ! $manageSettings) {
            return [];
        }

        try {
            $models   = $manageModels ? service('modelStore')->all() : [];
            $indexing = [];
            foreach ($models as $model) {
                // Asking StarDust costs a query, so only models that want an indexed field are asked.
                if (FilterableFields::read(is_string($model['fields'] ?? null) ? $model['fields'] : null)['fields'] === []) {
                    continue;
                }
                $waiting = array_keys(array_filter(service('modelStore')->filterableState((int) $model['id']), static fn (string $state): bool => $state === 'indexing'));
                if ($waiting !== []) {
                    $indexing[(int) $model['id']] = $waiting;
                }
            }

            return DashboardAttention::items($models, $indexing, service('modules')->skipped(), $manageModels, $manageSettings);
        } catch (Throwable $e) {
            log_message('warning', 'The dashboard could not work out what needs attention. {error}', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function panels(): DashboardPanels
    {
        $panels = new DashboardPanels(static fn (string $ability): bool => can($ability));

        try {
            service('slots')->fill(Slots::DASHBOARD_PANELS, $panels);
        } catch (Throwable $e) {
            // A module that failed in its own code leaves out only its panels, and those added before stay.
            log_message('error', 'A module failed while adding dashboard panels. {error}', ['error' => $e->getMessage()]);
        }

        return $panels;
    }
}
