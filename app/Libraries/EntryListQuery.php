<?php

namespace App\Libraries;

use App\Data\Entries\FieldFilters;
use App\Data\Entries\ScanLimitExceeded;
use App\Data\EntryStore;
use App\Fields\FieldTypes;
use InvalidArgumentException;
use StarDust\Filter\QueryFilterValidationException;
use StarDust\Read\SortDirection;
use StarDust\Read\SortSpec;

/**
 * Answers the request of a model's entry list: what the person typed, which column they sorted by, which filters they
 * set and which page they are on.
 *
 * `parse()` turns the query string of the page into a request that is safe to use, and `run()` reads the page. A search
 * or a sort on a field that is not indexed scans the entries of the model. A filter on indexed fields reads through the
 * index, and then the search is not available, because StarDust can only order and filter by an index.
 */
final class EntryListQuery
{
    public const PER_PAGE = [10, 25, 50, 100];

    /**
     * The columns of an entry that are not fields, which a list can sort by.
     */
    public const STATIC_SORTS = ['date_modified', 'edited_by'];

    /**
     * Types whose values order sensibly as a text, a number or a date.
     */
    private const SORTABLE = ['text', 'number', 'datetime', 'color', 'hook', 'choice'];

    public function __construct(private readonly EntryStore $store, private readonly FieldTypes $types)
    {
    }

    /**
     * Whether a list can be sorted by a field of the model.
     *
     * @param array<string, mixed> $field One entry of the model definition.
     */
    public function sortable(array $field): bool
    {
        $type = $field['type'] ?? '';

        return in_array($type, self::SORTABLE, true) && ! ($type === 'choice' && ! empty($field['multiple']));
    }

    /**
     * Read the query string of the list page.
     *
     * @param array<string, mixed>       $query   The GET values.
     * @param list<array<string, mixed>> $fields  The model's definition.
     * @param int                        $default The rows per page this user chose.
     *
     * @return array{q: string, sort: string, dir: 'asc'|'desc', page: int, per: int, trash: bool, filter: array<string, array<string, string>>}
     */
    public function parse(array $query, array $fields, int $default = 25): array
    {
        $byId = [];
        foreach ($fields as $field) {
            if (is_string($field['id'] ?? null)) {
                $byId[$field['id']] ??= $field;
            }
        }

        $q = is_string($query['q'] ?? null) ? mb_substr(trim($query['q']), 0, 200) : '';

        $sort = is_string($query['sort'] ?? null) ? $query['sort'] : '';
        if (! in_array($sort, self::STATIC_SORTS, true) && ! (isset($byId[$sort]) && $this->sortable($byId[$sort]))) {
            $sort = '';
        }
        $dir = ($query['dir'] ?? null) === 'asc' ? 'asc' : (($query['dir'] ?? null) === 'desc' ? 'desc' : ($sort === '' || $sort === 'date_modified' ? 'desc' : 'asc'));

        $per = (int) ($query['per'] ?? $default);
        $per = in_array($per, self::PER_PAGE, true) ? $per : (in_array($default, self::PER_PAGE, true) ? $default : 25);

        $trash = in_array($query['trash'] ?? null, ['1', 1, true, 'true'], true);

        // A filter is kept only for a filterable field, with an operator that field's type offers and a value.
        $filter = [];
        foreach (is_array($query['f'] ?? null) ? $query['f'] : [] as $id => $conditions) {
            $field = $byId[(string) $id] ?? null;
            $type  = $field === null ? null : $this->types->get((string) ($field['type'] ?? ''));
            if ($type === null || empty($field['filterable']) || ! is_array($conditions)) {
                continue;
            }
            $allowed = array_column($type->filters($field), 'op');
            foreach ($conditions as $op => $value) {
                if (in_array((string) $op, $allowed, true) && is_string($value) && trim($value) !== '') {
                    $filter[(string) $id][(string) $op] = trim($value);
                }
            }
        }

        return ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'page' => max(1, (int) ($query['page'] ?? 1)), 'per' => $per, 'trash' => $trash, 'filter' => $filter];
    }

    /**
     * Read one page of a model's entries.
     *
     * @param array{q: string, sort: string, dir: string, page: int, per: int, trash: bool, filter: array<string, array<string, string>>} $request A result of `parse()`.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, matching: int|null, notice: string, error: string}
     *         A row holds `id`, `fields` and the columns `edited_by`, `created_by`, `date_modified`, `date_deleted` and
     *         `deleted_by`. `matching` is null when StarDust could not count the matches. A `notice` explains what the page
     *         did differently from the request, and an `error` explains why it has no rows.
     */
    public function run(int $modelId, array $request): array
    {
        $empty = ['rows' => [], 'total' => 0, 'matching' => 0, 'notice' => '', 'error' => ''];

        try {
            if ($request['filter'] !== [] && ! $request['trash']) {
                return $this->indexed($modelId, $request) + $empty;
            }

            return $this->scanned($modelId, $request) + $empty;
        } catch (ScanLimitExceeded $e) {
            return ['error' => $e->getMessage()] + $empty;
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()] + $empty;
        } catch (QueryFilterValidationException $e) {
            return ['error' => lang('Filterable.filterRefused', ['reason' => $e->getMessage()])] + $empty;
        }
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function indexed(int $modelId, array $request): array
    {
        $indexed = $this->store->indexedFields($modelId);
        $ast     = FieldFilters::parse($request['filter'], $indexed);
        $notice  = $request['q'] !== '' ? lang('Filterable.noSearchWithField') : '';

        $direction = $request['dir'] === 'asc' ? SortDirection::Asc : SortDirection::Desc;
        if ($request['sort'] !== '' && isset($indexed[$request['sort']])) {
            $sort = SortSpec::byField($request['sort'], $direction);
        } else {
            $sort   = SortSpec::byId($direction);
            $notice = $notice !== '' || $request['sort'] === '' ? $notice : lang('Filterable.sortKeyWithField', ['sort' => $request['sort']]);
        }

        $page = $this->store->fieldPage($modelId, [], $sort, $ast, $request['per'], $request['page'], (int) config('HyperEngine')->filterCountLimit);

        return [
            'rows'     => array_map($this->row(...), $page['data']),
            'total'    => $this->store->countForModel($modelId),
            'matching' => $page['total'],
            'notice'   => $notice,
        ];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function scanned(int $modelId, array $request): array
    {
        $column = $request['sort'] !== '' ? $request['sort'] : 'date_modified';
        $result = $this->store->modelDataTable([
            'id'      => $modelId,
            'draw'    => 1,
            'start'   => ($request['page'] - 1) * $request['per'],
            'length'  => $request['per'],
            'search'  => ['value' => $request['q']],
            'trash'   => $request['trash'] ? 'true' : 'false',
            'order'   => [['column' => 0, 'dir' => $request['dir']]],
            'columns' => [['data' => $column]],
            'pivot'   => false,
        ]);

        return [
            'rows'     => array_map($this->row(...), $result['data']),
            'total'    => (int) $result['recordsTotal'],
            'matching' => (int) $result['recordsFiltered'],
        ];
    }

    /**
     * One row of the list, with its fields decoded.
     *
     * @param array<string, mixed> $row A legacy row, with the fields as JSON text.
     *
     * @return array<string, mixed>
     */
    private function row(array $row): array
    {
        $fields = json_decode((string) ($row['fields'] ?? ''), true);

        return [
            'id'            => (int) $row['id'],
            'fields'        => is_array($fields) && ! array_is_list($fields) ? $fields : [],
            'created_by'    => $row['created_by'] ?? null,
            'edited_by'     => $row['edited_by'] ?? null,
            'deleted_by'    => $row['deleted_by'] ?? null,
            'date_modified' => $row['date_modified'] ?? null,
            'date_deleted'  => $row['date_deleted'] ?? null,
        ];
    }
}
