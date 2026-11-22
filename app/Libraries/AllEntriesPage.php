<?php

namespace App\Libraries;

use App\Fields\FieldTypes;

/**
 * Builds what the list of every entry draws: one row for each entry of the models the person may open, with its name, its
 * model and its last edit. Searching reads the text of the fields, and a filter narrows the list to one model.
 */
final class AllEntriesPage
{
    public const SORTS = ['model_name', 'date_modified'];

    public function __construct(private readonly FieldTypes $types)
    {
    }

    /**
     * @param array<string, mixed> $input The query string.
     *
     * @return array<string, mixed>
     */
    public function build(array $input, int $defaultPerPage): array
    {
        $models = [];
        foreach (service('modelStore')->all() as $model) {
            if (can('entries.read', (int) $model['id'])) {
                $models[(int) $model['id']] = $model;
            }
        }

        $q     = is_string($input['q'] ?? null) ? mb_substr(trim($input['q']), 0, 200) : '';
        $model = (int) ($input['model'] ?? 0);
        $model = isset($models[$model]) ? $model : 0;
        $sort  = in_array($input['sort'] ?? '', self::SORTS, true) ? $input['sort'] : '';
        $dir   = ($input['dir'] ?? '') === 'asc' ? 'asc' : (($input['dir'] ?? '') === 'desc' ? 'desc' : ($sort === 'model_name' ? 'asc' : 'desc'));
        $per   = (int) ($input['per'] ?? $defaultPerPage);
        $per   = in_array($per, EntryListQuery::PER_PAGE, true) ? $per : 25;
        $page  = max(1, (int) ($input['page'] ?? 1));

        $hidden = array_values(array_diff(array_map(static fn (array $m): int => (int) $m['id'], service('modelStore')->all()), array_keys($models)));
        $result = service('entryStore')->dataTable([
            'draw'     => 1,
            'start'    => ($page - 1) * $per,
            'length'   => $per,
            'search'   => ['value' => $q],
            'model_id' => $model > 0 ? $model : null,
            'order'    => [['column' => 0, 'dir' => $dir]],
            'columns'  => [['data' => $sort !== '' ? $sort : 'date_modified']],
        ], $hidden);

        $rows = [];
        foreach ($result['data'] as $row) {
            $rows[] = $this->row($row, $models);
        }

        $request = ['q' => $q, 'model' => $model, 'sort' => $sort, 'dir' => $dir, 'per' => $per, 'page' => $page];
        $base    = base_url('admin/entries');

        return [
            'request'  => $request,
            'models'   => $models,
            'rows'     => $rows,
            'total'    => (int) $result['recordsTotal'],
            'matching' => (int) $result['recordsFiltered'],
            'columns'  => [
                ['key' => '__label', 'label' => lang('Entries.entry'), 'class' => 'label-cell'],
                $this->sortable(['key' => 'model_name', 'label' => lang('Entries.model')], 'model_name', $request, $base),
                $this->sortable(['key' => '__edited', 'label' => lang('Entries.edited')], 'date_modified', $request, $base),
            ],
            'urls'     => ['list' => $base, 'page' => $this->url($base, $request, ['page' => '{page}'])],
            'htmx'     => ['hx-target' => '#entries', 'hx-swap' => 'outerHTML', 'hx-push-url' => 'true'],
            'links'    => ['new' => base_url('admin/model') . '/{id}/new'],
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $override
     */
    public function url(string $base, array $request, array $override = []): string
    {
        $values = array_merge($request, $override);
        $query  = [];
        if ($values['q'] !== '') {
            $query['q'] = $values['q'];
        }
        if ($values['model'] > 0) {
            $query['model'] = $values['model'];
        }
        if ($values['sort'] !== '') {
            $query['sort'] = $values['sort'];
            $query['dir']  = $values['dir'];
        }
        if ($values['per'] !== 25) {
            $query['per'] = $values['per'];
        }

        $page = $values['page'] === '{page}' ? 'page={page}' : ((int) $values['page'] > 1 ? 'page=' . (int) $values['page'] : '');
        $text = http_build_query($query);

        return $base . ($text === '' && $page === '' ? '' : '?' . $text . ($text !== '' && $page !== '' ? '&' : '') . $page);
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function sortable(array $column, string $key, array $request, string $base): array
    {
        $current = $request['sort'] === $key || ($request['sort'] === '' && $key === 'date_modified');
        $next    = $current && $request['dir'] === 'asc' ? 'desc' : ($current ? 'asc' : ($key === 'date_modified' ? 'desc' : 'asc'));

        return $column + ['href' => $this->url($base, $request, ['sort' => $key, 'dir' => $next, 'page' => 1]), 'dir' => $current ? $request['dir'] : null];
    }

    /**
     * @param array<string, mixed>               $row
     * @param array<int, array<string, mixed>>   $models
     *
     * @return array<string, mixed>
     */
    private function row(array $row, array $models): array
    {
        $id     = (int) $row['id'];
        $model  = $models[(int) $row['model_id']] ?? ['id' => (int) $row['model_id'], 'name' => (string) $row['model_name'], 'label_field' => null];
        $fields = json_decode((string) ($row['fields'] ?? ''), true);
        $fields = is_array($fields) && ! array_is_list($fields) ? $fields : [];
        $field  = is_string($model['label_field'] ?? null) ? $model['label_field'] : null;
        $name   = $field !== null && is_scalar($fields[$field] ?? null) && (string) $fields[$field] !== '' ? (string) $fields[$field] : '#' . $id;
        $edit   = base_url('admin/model/' . (int) $row['model_id'] . '/' . $id . '/edit');

        $date = $this->types->get('datetime')->cell($row['date_modified'] ?? null, ['id' => 'x', 'type' => 'datetime']);
        $by   = $row['edited_by'] ?? null;

        return [
            'id'      => $id,
            'label'   => $name,
            'cells'   => [
                '__label'    => '<a href="' . attr($edit) . '">' . esc($name) . '</a>',
                'model_name' => '<a class="muted" href="' . attr(base_url('admin/model/' . (int) $row['model_id'])) . '">' . esc((string) $model['name']) . '</a>',
                '__edited'   => $date . ($by !== null && $by !== '' ? ' <span class="muted">' . esc(lang('Entries.by', ['name' => (string) $by])) . '</span>' : ''),
            ],
            'actions' => ui('button', ['label' => lang('Entries.edit'), 'href' => $edit, 'variant' => 'quiet', 'size' => 'small']),
        ];
    }
}
