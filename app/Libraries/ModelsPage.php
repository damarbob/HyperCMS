<?php

namespace App\Libraries;

use App\Fields\FieldTypes;

/**
 * Builds what the list of models draws: the columns, the rows as HTML cells, and the addresses the page links to.
 *
 * The page and the actions that change the list (trash, restore, empty the trash) use the same builder, so each can
 * answer with the list as it looks after the change, under the same search, sort and page.
 */
final class ModelsPage
{
    private const SORTS = ['name', 'group', 'date_modified'];

    /**
     * @param array<string, mixed> $input The query string, or the values of a form that carries the same names.
     *
     * @return array<string, mixed>
     */
    public function build(array $input, int $defaultPerPage): array
    {
        $trash = ! empty($input['trash']) && (string) $input['trash'] !== '0';
        $q     = trim((string) ($input['q'] ?? ''));
        $sort  = in_array($input['sort'] ?? '', self::SORTS, true) ? (string) $input['sort'] : '';
        $dir   = ($input['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
        if ($sort === 'name' || $sort === 'group') {
            $dir = ($input['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        }
        $per  = in_array((int) ($input['per'] ?? 0), [10, 25, 50, 100], true) ? (int) $input['per'] : $defaultPerPage;
        $page = max(1, (int) ($input['page'] ?? 1));

        $request = ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per' => $per, 'page' => $page, 'trash' => $trash];

        $result = service('modelStore')->dataTable([
            'trash'   => $trash ? 'true' : 'false',
            'start'   => ($page - 1) * $per,
            'length'  => $per,
            'search'  => ['value' => $q],
            'order'   => $sort === '' ? null : [['column' => 0, 'dir' => $dir]],
            'columns' => [['data' => $sort === 'date_modified' ? 'date_modified' : $sort]],
        ]);

        $base   = base_url('admin/models');
        $counts = service('entryStore')->countsByModel();

        $columns = [
            $this->withSort(['key' => 'name', 'label' => lang('Models.name'), 'class' => 'label-cell'], 'name', $request, $base),
            $this->withSort(['key' => 'group', 'label' => lang('Models.group')], 'group', $request, $base),
            ['key' => 'fields', 'label' => lang('Models.fieldsColumn'), 'class' => 'num'],
        ];
        if (! $trash) {
            $columns[] = ['key' => 'entries', 'label' => lang('Models.entriesColumn'), 'class' => 'num'];
        }
        $columns[] = $trash
            ? ['key' => 'edited', 'label' => lang('Models.deleted')]
            : $this->withSort(['key' => 'edited', 'label' => lang('Models.edited')], 'date_modified', $request, $base);

        $rows = [];
        foreach ($result['data'] as $row) {
            $rows[] = $this->tableRow($row, $counts, $trash);
        }

        return [
            'request' => $request,
            'total'   => (int) $result['recordsFiltered'],
            'columns' => $columns,
            'rows'    => $rows,
            'htmx'    => ['hx-target' => '#models', 'hx-swap' => 'outerHTML', 'hx-push-url' => 'true'],
            'urls'    => [
                'list'    => $base,
                'page'    => $this->url($base, $request, ['page' => '{page}']),
                'live'    => $this->url($base, $request, ['trash' => false, 'page' => 1]),
                'bin'     => $this->url($base, $request, ['trash' => true, 'page' => 1]),
                'new'     => $base . '/new',
                'trash'   => $base . '/delete',
                'restore' => $base . '/restore',
                'purge'   => $base . '/purge-deleted',
            ],
            'live'    => $trash ? null : (int) $result['recordsTotal'],
            'trashed' => (int) service('modelStore')->countTrashed(),
        ];
    }

    /**
     * The address of the list with the request's values, and some of them replaced. A value that is the default is
     * left out, so addresses stay short.
     *
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
        if ((string) $values['page'] === '{page}') {
            $query['page'] = '{page}';
        } elseif ((int) $values['page'] > 1) {
            $query['page'] = (int) $values['page'];
        }

        // The pager puts the page number where `{page}` stands, so the braces stay as they are.
        return $base . ($query === [] ? '' : '?' . str_replace(['%7Bpage%7D'], ['{page}'], http_build_query($query)));
    }

    /**
     * @param array<string, mixed> $column
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function withSort(array $column, string $sortKey, array $request, string $base): array
    {
        if ($request['trash']) {
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
     * @param array<string, mixed> $row
     * @param array<int, int>      $counts
     *
     * @return array<string, mixed>
     */
    private function tableRow(array $row, array $counts, bool $trash): array
    {
        $id      = (int) $row['id'];
        $edit    = base_url('admin/models/' . $id . '/edit');
        $name    = (string) $row['name'];
        $fields  = json_decode((string) $row['fields'], true);
        $entries = $counts[$id] ?? 0;

        $label = icon(RailMenu::icon((string) ($row['icon'] ?? ''))) . ($trash ? '<span>' . esc($name) . '</span>' : '<a href="' . attr($edit) . '">' . esc($name) . '</a>');
        $stamp = $trash ? $row['date_deleted'] : $row['date_modified'];
        $by    = $trash ? $row['deleted_by'] : $row['edited_by'];
        $when  = $stamp === null || $stamp === '' ? '' : FieldTypes::core()->get('datetime')->cell((string) $stamp, ['id' => 'x', 'type' => 'datetime']);

        $cells = [
            'name'   => '<span class="model-name">' . $label . '</span>',
            'group'  => (string) ($row['group'] ?? '') === '' ? '<span class="muted">' . esc(lang('Models.noGroup')) . '</span>' : esc((string) $row['group']),
            'fields' => (string) (is_array($fields) ? count($fields) : 0),
            'edited' => $when . ($by !== null && $by !== '' ? ' <span class="muted">' . esc(lang('Models.by', ['name' => (string) $by])) . '</span>' : ''),
        ];
        if (! $trash) {
            $cells['entries'] = '<a href="' . attr(base_url('admin/model/' . $id)) . '">' . $entries . '</a>';
        }

        return [
            'id'      => $id,
            'label'   => $name,
            'cells'   => $cells,
            'actions' => $trash ? '' : ui('button', ['label' => lang('Models.edit'), 'href' => $edit, 'variant' => 'quiet', 'size' => 'small']),
        ];
    }
}
