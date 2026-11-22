<?php

namespace App\Libraries;

use App\Fields\FieldTypes;

/**
 * Builds what the list page of one model draws: its columns, its rows as HTML cells, its filter controls, the chips of
 * the filters that are applied, and the addresses the page links to.
 *
 * The page and the actions that change the list (trash and restore) use the same builder, so each can answer with the
 * list as it looks after the change, under the same search, sort, filters and page.
 */
final class ModelListPage
{
    /**
     * Types that are not shown as a column unless the definition says `list`. Their values are long or not for people.
     */
    private const QUIET_TYPES = ['longtext', 'richtext', 'code', 'hidden'];

    private readonly EntryListQuery $query;

    public function __construct(private readonly FieldTypes $types)
    {
        $this->query = new EntryListQuery(service('entryStore'), $types);
    }

    /**
     * @param array<string, mixed> $model The model as `ModelStore::findArray()` returns it.
     * @param array<string, mixed> $input The query string, or the values of a form that carries the same names.
     *
     * @return array<string, mixed>
     */
    public function build(array $model, array $input, int $defaultPerPage): array
    {
        $fields  = $this->definition((string) ($model['fields'] ?? ''));
        $request = $this->query->parse($input, $fields, $defaultPerPage);
        $result  = $this->query->run((int) $model['id'], $request);

        $labelField = is_string($model['label_field'] ?? null) && $model['label_field'] !== '' ? $model['label_field'] : null;
        $base       = base_url('admin/model/' . (int) $model['id']);
        $htmx       = ['hx-target' => '#entries', 'hx-swap' => 'outerHTML', 'hx-push-url' => 'true'];

        // Entries that relations point at are read together, so each cell does not ask for its own label.
        $relationIds = [];
        foreach ($fields as $field) {
            if (in_array($field['type'] ?? '', ['relation'], true)) {
                foreach ($result['rows'] as $row) {
                    foreach ((array) ($row['fields'][$field['id']] ?? []) as $id) {
                        $relationIds[] = $id;
                    }
                }
            }
        }
        service('entryLabels')->preload($relationIds);

        $columns = [['key' => '__label', 'label' => $this->labelHeading($fields, $labelField), 'class' => 'label-cell']];
        foreach ($fields as $field) {
            if (($field['id'] ?? '') === $labelField || ! $this->listed($field)) {
                continue;
            }
            $column = ['key' => (string) $field['id'], 'label' => (string) ($field['label'] ?? $field['id'])];
            if (($field['type'] ?? '') === 'number') {
                $column['class'] = 'num';
            }
            $columns[] = $this->withSort($column, (string) $field['id'], $this->query->sortable($field), $request, $base);
        }
        $columns[] = $this->withSort(['key' => '__edited', 'label' => lang($request['trash'] ? 'Entries.deleted' : 'Entries.edited')], $request['trash'] ? '' : 'date_modified', ! $request['trash'], $request, $base);

        $rows = [];
        foreach ($result['rows'] as $row) {
            $rows[] = $this->tableRow($row, $fields, $labelField, $request, $model);
        }

        return [
            'model'      => $model,
            'fields'     => $fields,
            'request'    => $request,
            'result'     => $result,
            'columns'    => $columns,
            'rows'       => $rows,
            'controls'   => $this->controls($fields, $request),
            'applied'    => $this->applied($fields, $request, $base),
            'note'       => $this->note($fields, $request),
            'htmx'       => $htmx,
            'urls'       => [
                'list'    => $base,
                'page'    => $this->url($base, $request, ['page' => '{page}']),
                'new'     => $base . '/new',
                'export'  => $this->url($base . '/export', $request, ['page' => 1]),
                'trash'   => $base . '/trash',
                'restore' => $base . '/restore',
                'live'    => $this->url($base, $request, ['trash' => false, 'page' => 1, 'sort' => '', 'f' => []]),
                'bin'     => $this->url($base, $request, ['trash' => true, 'page' => 1, 'sort' => '', 'f' => []]),
            ],
            'labelField' => $labelField,
        ];
    }

    /**
     * `build()` plus what the person may do and the address of the New button, which is all the list's views need.
     *
     * @param array<string, mixed> $model
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function viewData(array $model, array $input, int $defaultPerPage): array
    {
        $data = $this->build($model, $input, $defaultPerPage);
        $id   = (int) $model['id'];

        $data['can']   = ['create' => can('entries.create', $id), 'delete' => can('entries.delete', $id), 'purge' => can('entries.purge')];
        $data['id']    = $id;
        $data['links'] = ['new' => $data['urls']['new']];

        return $data;
    }
    /**
     * The fields of the model's definition that are drawn, in order.
     *
     * @return list<array<string, mixed>>
     */
    public function definition(string $json): array
    {
        $fields = json_decode($json, true);

        return is_array($fields) ? array_values(array_filter($fields, static fn ($f): bool => is_array($f) && is_string($f['id'] ?? null))) : [];
    }

    /**
     * The address of the list with the request's values, and some of them replaced. A value that is the default is left
     * out, so addresses stay short.
     *
     * @param array<string, mixed> $request
     * @param array<string, mixed> $override
     */
    public function url(string $base, array $request, array $override = []): string
    {
        $values = array_merge($request, array_diff_key($override, ['f' => 1]));
        if (array_key_exists('f', $override)) {
            $values['filter'] = $override['f'];
        }

        $query = [];
        if ($values['q'] !== '') {
            $query['q'] = $values['q'];
        }
        if ($values['sort'] !== '') {
            $query['sort'] = $values['sort'];
            $query['dir']  = $values['dir'];
        }
        if ($values['per'] !== 25) {
            $query['per'] = $values['per'];
        }
        if ($values['trash']) {
            $query['trash'] = 1;
        }
        if ($values['filter'] !== []) {
            $query['f'] = $values['filter'];
        }

        // The number of a page may be a placeholder that a pager fills in, so it is not escaped with the rest.
        $page = $values['page'] === '{page}' ? 'page={page}' : ((int) $values['page'] > 1 ? 'page=' . (int) $values['page'] : '');
        $text = http_build_query($query);

        return $base . ($text === '' && $page === '' ? '' : '?' . $text . ($text !== '' && $page !== '' ? '&' : '') . $page);
    }

    /**
     * Whether a field is a column of the list. The definition's `list` decides, and without it the type does.
     *
     * @param array<string, mixed> $field
     */
    private function listed(array $field): bool
    {
        if (array_key_exists('list', $field)) {
            return filter_var($field['list'], FILTER_VALIDATE_BOOLEAN);
        }

        return ! in_array($field['type'] ?? '', self::QUIET_TYPES, true);
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private function labelHeading(array $fields, ?string $labelField): string
    {
        foreach ($fields as $field) {
            if (($field['id'] ?? null) === $labelField) {
                return (string) ($field['label'] ?? $field['id']);
            }
        }

        return lang('Entries.entry');
    }

    /**
     * Add the sort link and direction to a column that can be sorted.
     *
     * @param array<string, mixed> $column
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function withSort(array $column, string $sortKey, bool $sortable, array $request, string $base): array
    {
        if (! $sortable || $sortKey === '') {
            return $column;
        }

        $current = $request['sort'] === $sortKey || ($request['sort'] === '' && $sortKey === 'date_modified');
        $next    = $current && $request['dir'] === 'asc' ? 'desc' : ($current ? 'asc' : ($sortKey === 'date_modified' ? 'desc' : 'asc'));

        return $column + [
            'href' => $this->url($base, $request, ['sort' => $sortKey, 'dir' => $next, 'page' => 1]),
            'dir'  => $current ? $request['dir'] : null,
        ];
    }

    /**
     * @param array<string, mixed>       $row
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>       $request
     * @param array<string, mixed>       $model
     *
     * @return array<string, mixed>
     */
    private function tableRow(array $row, array $fields, ?string $labelField, array $request, array $model): array
    {
        $id   = (int) $row['id'];
        $edit = base_url('admin/model/' . (int) $model['id'] . '/' . $id . '/edit');

        $name = $labelField !== null && is_scalar($row['fields'][$labelField] ?? null) && (string) $row['fields'][$labelField] !== '' ? (string) $row['fields'][$labelField] : '#' . $id;
        $cells = ['__label' => $request['trash'] ? esc($name) : '<a href="' . attr($edit) . '">' . esc($name) . '</a>'];

        foreach ($fields as $field) {
            if (($field['id'] ?? '') === $labelField || ! $this->listed($field)) {
                continue;
            }
            $type = $this->types->get((string) ($field['type'] ?? ''));
            $cells[(string) $field['id']] = $type === null ? esc((string) json_encode($row['fields'][$field['id']] ?? null)) : $type->cell($row['fields'][$field['id']] ?? null, $field);
        }

        $stamp = $request['trash'] ? $row['date_deleted'] : $row['date_modified'];
        $by    = $request['trash'] ? $row['deleted_by'] : $row['edited_by'];
        $date  = $this->types->get('datetime')->cell($stamp, ['id' => '__edited', 'type' => 'datetime']);
        $cells['__edited'] = $date . ($by !== null && $by !== '' ? ' <span class="muted">' . esc(lang('Entries.by', ['name' => (string) $by])) . '</span>' : '');

        return [
            'id'      => $id,
            'label'   => $name,
            'cells'   => $cells,
            'actions' => $request['trash'] ? '' : ui('button', ['label' => lang('Entries.edit'), 'href' => $edit, 'variant' => 'quiet', 'size' => 'small']),
        ];
    }

    /**
     * The controls of the filters, one group for each filterable field.
     *
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>       $request
     */
    private function controls(array $fields, array $request): string
    {
        $html = '';
        foreach ($fields as $field) {
            $type = $this->types->get((string) ($field['type'] ?? ''));
            if ($type === null || empty($field['filterable'])) {
                continue;
            }

            $group = '';
            foreach ($type->filters($field) as $filter) {
                $name  = 'f[' . $field['id'] . '][' . $filter['op'] . ']';
                $value = $request['filter'][$field['id']][$filter['op']] ?? '';
                $id    = 'flt-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $field['id'] . '-' . $filter['op']);

                if ($filter['control'] === 'select') {
                    $options = '<option value="">' . esc(lang('Entries.any')) . '</option>';
                    foreach ($filter['choices'] ?? [] as $key => $label) {
                        $options .= '<option value="' . attr((string) $key) . '"' . ((string) $key === $value ? ' selected' : '') . '>' . esc($label) . '</option>';
                    }
                    $control = '<select class="select" id="' . attr($id) . '" name="' . attr($name) . '">' . $options . '</select>';
                } else {
                    $control = '<input class="input" id="' . attr($id) . '" type="' . attr($filter['control']) . '" name="' . attr($name) . '" value="' . attr($value) . '">';
                }
                $group .= '<div class="field"><label for="' . attr($id) . '">' . esc($filter['label']) . '</label>' . $control . '</div>';
            }

            if ($group !== '') {
                $html .= '<fieldset class="filter-group"><legend>' . esc((string) ($field['label'] ?? $field['id'])) . '</legend>' . $group . '</fieldset>';
            }
        }

        return $html;
    }

    /**
     * The chips of the applied filters, each with the address of the list without it.
     *
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>       $request
     *
     * @return list<array<string, mixed>>
     */
    private function applied(array $fields, array $request, string $base): array
    {
        $byId = [];
        foreach ($fields as $field) {
            $byId[$field['id']] = $field;
        }

        $chips = [];
        foreach ($request['filter'] as $id => $conditions) {
            $field = $byId[$id];
            $type  = $this->types->get((string) $field['type']);
            $descs = array_column($type?->filters($field) ?? [], null, 'op');

            foreach ($conditions as $op => $value) {
                $shown = isset($descs[$op]['choices'][$value]) ? $descs[$op]['choices'][$value] : $value;
                $rest  = $request['filter'];
                unset($rest[$id][$op]);
                $rest = array_filter($rest);

                $chips[] = [
                    'label'       => ($field['label'] ?? $id) . ' ' . mb_strtolower((string) ($descs[$op]['label'] ?? $op)) . ' ' . $shown,
                    'removeHref'  => $this->url($base, $request, ['f' => $rest, 'page' => 1]),
                    'removeLabel' => lang('Entries.removeFilter', ['name' => (string) ($field['label'] ?? $id)]),
                ];
            }
        }

        return $chips;
    }

    /**
     * The line beside the filters that says how the list is sorted.
     *
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>       $request
     */
    private function note(array $fields, array $request): string
    {
        $key = $request['sort'] !== '' ? $request['sort'] : 'date_modified';
        $name = match ($key) {
            'date_modified' => lang('Entries.edited'),
            'edited_by'     => lang('Entries.editedBy'),
            default         => (string) (array_column($fields, 'label', 'id')[$key] ?? $key),
        };

        return lang('Entries.sortedBy', ['field' => $name, 'direction' => lang($request['dir'] === 'asc' ? 'Entries.ascending' : 'Entries.descending')]);
    }
}
