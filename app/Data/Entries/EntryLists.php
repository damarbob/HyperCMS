<?php

namespace App\Data\Entries;

use App\Constants\EntryDataStaticFields;
use App\Constants\ModelStaticFields;
use App\Data\LookupException;
use App\Data\WriteVersions;
use App\Libraries\SortColumn;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use InvalidArgumentException;
use StarDust\Filter\Ast\FilterNode;
use StarDust\Read\SortDirection;
use StarDust\Read\SortSpec;

/**
 * Lists of entries for the admin tables and the token API.
 *
 * A list with no search and no sort on a model field is answered in SQL over hyper_entries, with COUNT for the totals,
 * LIMIT and OFFSET for the page, and one getMany() for the fields of that page. A search, a find filter or a sort
 * on a model field needs the fields themselves, so those use a saved list: the ordered ids that one search and
 * sort matched, built once by scanning the entries and kept in the cache under the write version of the model, or
 * the global one for a list across models. The trash view stays in SQL, because trashed fields live in HyperCMS.
 *
 * A sort on a field the model marks filterable, once StarDust has indexed it, is the exception. StarDust orders the
 * entries itself, so the list is read through the index and needs no scan, and the page is fetched the same way.
 *
 * A saved list stores each id with its model id and ignores who is asking. Each request drops the models hidden from
 * the caller before it counts and pages.
 */
class EntryLists
{
    public function __construct(
        private ?BaseConnection $db = null,
        private ?EntryRows $rows = null,
        private ?EntryReader $reader = null,
        private ?EntryPayloads $payloads = null,
        private ?SavedLists $lists = null,
        private ?WriteVersions $versions = null,
        private ?ListScan $scan = null,
        private ?IndexedReads $indexed = null,
    ) {
    }

    /**
     * Answer one DataTables request for entries across all models.
     *
     * @param array $post The POST values DataTables sent (id, ids, model_id, draw, start, length, search, order, columns, trash).
     * @param array $hiddenModelIds Models whose entries the caller may not read.
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     */
    public function dataTable(array $post, array $hiddenModelIds = []): array
    {
        $id = $post['id'] ?? null;
        $ids = $post['ids'] ?? null;
        $modelId = $post['model_id'] ?? null;
        $draw = $post['draw'] ?? 1;
        $start = $post['start'] ?? null;
        $length = $post['length'] ?? -1;
        $search = $post['search']['value'] ?? '';
        $order = $post['order'] ?? null;
        $columns = $post['columns'] ?? null;
        $trash = $post['trash'] ?? false;

        // The trash view lists deleted entries. A bare `true` is read as live, as it always was, and only the text `true` opens the trash.
        $live = ! $trash || $trash == 'false';

        // DataTables sends the column index and direction, and the columns array names the
        // column. Only a plain column name may reach the query.
        $orderColumn = ! empty($order) ? ($columns[$order[0]['column'] ?? 0]['data'] ?? null) : null;
        $safe        = SortColumn::isSafe($orderColumn);
        $direction   = $order[0]['dir'] ?? '';

        $builder = $this->scoped($this->rows()->query(! $live), $id, $ids, $modelId, $hiddenModelIds);

        $totalRecords = $builder->countAllResults(false);

        $sortsFields = $safe && $orderColumn === 'fields';
        if ($live && (! empty($search) || $sortsFields)) {
            return $this->savedDataTable([
                'draw'      => $draw,
                'start'     => $start,
                'length'    => $length,
                'term'      => empty($search) ? '' : esc($search),
                'modelId'   => $modelId,
                'column'    => $safe ? $orderColumn : null,
                'direction' => $direction,
                'filters'   => [$id, $ids, $hiddenModelIds],
            ], $totalRecords);
        }

        // The three matches sit in one group. Without it their OR would let a row past the filters above.
        if (! empty($search)) {
            $builder->groupStart();
            $builder->like('m.name', esc($search));
            $builder->orLike($this->rows()->column('fields', true), esc($search));
            $builder->orLike($this->rows()->column('created_by'), esc($search));
            $builder->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        if ($safe) {
            $builder->orderBy($this->rows()->column($orderColumn, ! $live), $direction);
        } else {
            $builder->orderBy($this->rows()->column('date_modified', ! $live), 'DESC');
        }
        $builder->orderBy('e.id', 'ASC');

        // A length of -1 means no limit.
        if ($length != -1) {
            $builder->limit(intval($length), intval($start));
        }

        return [
            'draw'            => intval($draw),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $this->rows()->hydrate($builder->get()->getResultArray(), ! $live),
        ];
    }

    /**
     * A search or a sort on the fields column of the live entries, answered from a saved list.
     *
     * @param array{draw: mixed, start: mixed, length: mixed, term: string, modelId: mixed, column: string|null, direction: mixed, filters: array} $request
     */
    private function savedDataTable(array $request, int|string $totalRecords): array
    {
        $term      = $request['term'];
        $column    = $request['column'];
        $direction = $this->direction($request['direction']);
        $modelId   = ! empty($request['modelId']) ? (int) $request['modelId'] : null;

        $sort     = null;
        $sqlOrder = ['date_modified', 'DESC'];
        if ($column === 'fields') {
            $sort     = ['kind' => 'fields', 'field' => null, 'dir' => $direction];
            $sqlOrder = null;
        } elseif ($column !== null) {
            $sqlOrder = [$column, $direction];
        }

        // The search also matches the model name and the username, so a renamed user makes a list stale.
        $key = ['entries', $modelId ?? 'all', $term, $sort, $sqlOrder, $modelId === null ? $this->versions()->global() : [$this->versions()->forModel($modelId), $this->versions()->global()]];

        $list = $this->lists()->remember($key, function () use ($modelId, $term, $sort, $sqlOrder): array {
            $candidates = $this->rows()->lean();
            if ($modelId !== null) {
                $candidates->where('e.model_id', $modelId);
            }
            if ($sqlOrder !== null) {
                $candidates->orderBy($this->rows()->column($sqlOrder[0]), $sqlOrder[1]);
            }
            $candidates->orderBy('e.id', 'ASC');

            $matched = [];
            if ($term !== '') {
                $names = $this->rows()->lean();
                if ($modelId !== null) {
                    $names->where('e.model_id', $modelId);
                }
                $names->groupStart()->like('m.name', $term)->orLike($this->rows()->column('created_by'), $term)->groupEnd();
                $matched = array_fill_keys(array_map('intval', array_column($names->get()->getResultArray(), 'id')), true);
            }

            return $this->scan()->run(
                $candidates->get()->getResultArray(),
                ['search' => $term, 'sort' => $sort],
                fn (array $chunk): array => $this->readLive($chunk),
                $matched,
            );
        });

        [$id, $ids, $hidden] = $request['filters'];
        $kept                = $this->dropFiltered($list['rows'], $id, $ids, $hidden);

        return [
            'draw'            => intval($request['draw']),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => count($kept),
            'data'            => $this->pageRows($kept, $request['start'], $request['length'], false),
        ];
    }

    /**
     * Answer one DataTables request for the entries of a single model.
     *
     * @param array $params The values to read (id, draw, start, length, search, order, columns, find, trash).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     * @throws \InvalidArgumentException When the model id is missing or names no model.
     */
    public function modelDataTable(array $params): array
    {
        $modelId = $params['id'] ?? null;
        $draw = $params['draw'] ?? 1;
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $search = $params['search']['value'] ?? ($params['search'] ?? '');
        $order = $params['order'] ?? null;
        $columns = $params['columns'] ?? null;
        $find = $params['find'] ?? null;
        $trash = $params['trash'] ?? false;

        if (! $modelId) {
            throw new InvalidArgumentException('No model id provided.');
        }

        $modelInfo     = $this->modelInfo($modelId);
        $dateFields    = $modelInfo['dateFields'];
        $numericFields = $modelInfo['numericFields'];
        $codeFields    = $modelInfo['codeFields'];

        $live = ! $trash || $trash == 'false';

        $find = is_array($find) && ! empty($find['field']) && ! empty($find['value']) ? ['field' => (string) $find['field'], 'value' => (string) $find['value']] : null;
        $term = esc($search);
        $term = empty($term) ? '' : $term;

        // The order. A fixed column is sorted in SQL, a model field from its fields, and with no order the newest edit comes first.
        $orderColumn = null;
        $orderDir    = 'DESC';
        $kind        = null;
        if (! empty($order)) {
            $orderColumnIndex = $order[0]['column'];
            $orderDir         = $order[0]['dir'];
            $orderColumn      = $columns[$orderColumnIndex]['data'];

            if (! in_array($orderColumn, ModelStaticFields::FIELD_LIST)) {
                $kind = in_array($orderColumn, $dateFields) ? 'date' : (in_array($orderColumn, $numericFields) ? 'number' : 'text');
            }
        }

        // A sort on an indexed field needs no scan. A search or a find filter still does, and keeps the saved list.
        if ($live && $find === null && $term === '' && $kind !== null && in_array((string) $orderColumn, $modelInfo['filterableFields'], true)
            && isset($this->indexedReads()->indexed((int) $modelId)[(string) $orderColumn])) {
            return $this->indexedModelDataTable((int) $modelId, (string) $orderColumn, $this->direction($orderDir), $draw, $start, $length, $codeFields);
        }

        if ($find === null && $term === '' && $kind === null) {
            return $this->sqlModelDataTable((int) $modelId, ! $live, $orderColumn, $orderDir, $draw, $start, $length, $codeFields);
        }

        $direction = $this->direction($orderDir);
        $sort      = $kind === null ? null : ['kind' => $kind, 'field' => (string) $orderColumn, 'dir' => $direction];
        $sqlOrder  = $kind === null ? [$orderColumn ?? 'date_modified', $orderColumn === null ? 'DESC' : $direction] : null;
        $usernames = $sqlOrder !== null && in_array($sqlOrder[0], ['edited_by', 'created_by', 'deleted_by'], true);

        $key = ['model', (int) $modelId, $live, $find, $term, $sort, $sqlOrder, $this->versions()->forModel((int) $modelId), $usernames ? $this->versions()->global() : null];

        $list = $this->lists()->remember($key, function () use ($modelId, $live, $find, $term, $sort, $sqlOrder): array {
            $candidates = $this->rows()->lean(! $live)->where('e.model_id', (int) $modelId);
            if ($sqlOrder !== null) {
                $candidates->orderBy($this->rows()->column($sqlOrder[0], ! $live), $sqlOrder[1]);
            }
            $candidates->orderBy('e.id', 'ASC');

            return $this->scan()->run(
                $candidates->get()->getResultArray(),
                ['find' => $find, 'search' => $term, 'sort' => $sort],
                $live ? fn (array $chunk): array => $this->readLive($chunk) : fn (array $chunk): array => $this->readSnapshots($chunk),
            );
        });

        return [
            'draw'            => intval($draw),
            'recordsTotal'    => $list['total'],
            'recordsFiltered' => count($list['rows']),
            'data'            => $this->pivotRows($this->pageRows($list['rows'], $start, $length, ! $live), $codeFields),
        ];
    }

    /**
     * The list of one model when nothing needs its fields: the order is a fixed column or none, and no find filter or search is set.
     */
    private function sqlModelDataTable(int $modelId, bool $trashed, mixed $orderColumn, mixed $orderDir, mixed $draw, mixed $start, mixed $length, array $codeFields): array
    {
        $builder = $this->rows()->query($trashed)->where('e.model_id', $modelId);

        $totalRecords = $builder->countAllResults(false);

        if ($orderColumn !== null) {
            $builder->orderBy($this->rows()->column((string) $orderColumn, $trashed), $orderDir);
        } else {
            $builder->orderBy($this->rows()->column('date_modified', $trashed), 'DESC');
        }
        $builder->orderBy('e.id', 'ASC');

        // A length of -1 means no limit.
        if ($length != -1) {
            $builder->limit(intval($length), intval($start));
        }

        return [
            'draw'            => intval($draw),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data'            => $this->pivotRows($this->rows()->hydrate($builder->get()->getResultArray(), $trashed), $codeFields),
        ];
    }

    /**
     * The list of one model sorted on a field StarDust has indexed.
     *
     * The totals count the model's live entries, as the other lists do, and the page is read through the index.
     */
    private function indexedModelDataTable(int $modelId, string $field, string $direction, mixed $draw, mixed $start, mixed $length, array $codeFields): array
    {
        $totalRecords = $this->rows()->query()->where('e.model_id', $modelId)->countAllResults();

        // A length of -1 means no limit, and a length of 0 has always meant the same for a sort on a field.
        $limit  = $length != -1 && intval($length) !== 0 ? intval($length) : null;
        $offset = $limit === null ? 0 : max(0, intval($start));

        $sort = SortSpec::byField($field, $direction === 'DESC' ? SortDirection::Desc : SortDirection::Asc);
        $ids  = $this->indexedReads()->page($modelId, $sort, null, $offset, $limit)['ids'];

        return [
            'draw'            => intval($draw),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data'            => $this->pivotRows($this->rowsOf($modelId, $ids), $codeFields),
        ];
    }

    /**
     * The rows of the live entries of a model that StarDust returned, in the order it returned them.
     *
     * An entry StarDust still returns after HyperCMS has trashed it, in the moment between the two steps of a trash,
     * has no live row and is left out.
     *
     * @param list<int> $sdIds StarDust entry ids.
     *
     * @return list<array> Legacy rows, with the fields read from StarDust.
     */
    private function rowsOf(int $modelId, array $sdIds): array
    {
        $bySd = [];
        foreach (array_chunk($sdIds, 1000) as $chunk) {
            foreach ($this->rows()->query()->where('e.model_id', $modelId)->whereIn('e.sd_entry_id', $chunk)->get()->getResultArray() as $row) {
                $bySd[(int) $row['sd_entry_id']] = $row;
            }
        }

        $ordered = [];
        foreach ($sdIds as $sdId) {
            if (isset($bySd[$sdId])) {
                $ordered[] = $bySd[$sdId];
            }
        }

        return $this->rows()->hydrate($ordered);
    }

    /**
     * Read one page of the entries of a model through its indexed fields, for the token API.
     *
     * The order is a sort on an indexed field, or StarDust's own order of creation. The search and the other sort
     * keys of `page()` are not available here, because StarDust can order by an index only.
     *
     * @param FilterNode|null $filter What the entries must match, on indexed fields.
     * @param int $countCap The most matches to count for the total. StarDust cannot count, so a larger match set has no total.
     *
     * @return array{data: list<array>, total: int|null, more: bool} `total` is null when the count stopped at the cap.
     */
    public function fieldPage(int $modelId, array $hiddenModelIds, ?SortSpec $sort, ?FilterNode $filter, int $limit, int $page, int $countCap): array
    {
        if (in_array($modelId, array_map('intval', $hiddenModelIds), true)) {
            return ['data' => [], 'total' => 0, 'more' => false];
        }

        $read  = $this->indexedReads()->page($modelId, $sort, $filter, ($page - 1) * $limit, $limit);
        $total = $filter === null
            ? $this->rows()->query()->where('e.model_id', $modelId)->countAllResults()
            : $this->indexedReads()->count($modelId, $filter, $countCap);

        return ['data' => $this->rowsOf($modelId, $read['ids']), 'total' => $total, 'more' => $read['more']];
    }

    /**
     * The fields of a model that can be sorted and filtered on right now, with the StarDust type of each.
     *
     * @return array<string, string> By field id.
     */
    public function indexedFields(int $modelId): array
    {
        return $this->indexedReads()->indexed($modelId);
    }

    /**
     * Read the model and sort its fields by type.
     */
    protected function modelInfo(int|string $modelId): array
    {
        $model = $this->db()->table('hyper_models')->select('fields')->where('id', $modelId)->where('deleted_at', null)->get()->getRow();

        if (! $model) {
            throw new InvalidArgumentException("Model with id $modelId not found.");
        }

        return $this->fieldTypes($model->fields);
    }

    /**
     * Sort a model definition's fields by type.
     *
     * @return array{dateFields: list<string>, numericFields: list<string>, codeFields: list<string>, filterableFields: list<string>}
     */
    private function fieldTypes(?string $definition): array
    {
        $dateFields    = [];
        $numericFields = [];
        $codeFields    = [];

        foreach (json_decode((string) $definition) ?? [] as $field) {
            if (! empty($field->type)) {
                if ($field->type === 'datetime-local') {
                    $dateFields[] = $field->id;
                } elseif ($field->type === 'number') {
                    $numericFields[] = $field->id;
                } elseif ($field->type === 'code') {
                    $codeFields[] = $field->id;
                }
            }
        }

        return ['dateFields' => $dateFields, 'numericFields' => $numericFields, 'codeFields' => $codeFields, 'filterableFields' => array_map('strval', array_keys(FilterableFields::read($definition)['fields']))];
    }

    /**
     * Spread each row's JSON fields into columns and escape the HTML of code fields.
     */
    protected function pivotRows(array $data, array $codeFields): array
    {
        foreach ($data as &$row) {
            if (isset($row['fields'])) {
                $fieldsArray = json_decode($row['fields'], true);

                if (is_array($fieldsArray)) {
                    // The older format is a list of objects with an id and a value.
                    if (array_is_list($fieldsArray) && ! empty($fieldsArray) && isset($fieldsArray[0]['id'])) {
                        foreach ($fieldsArray as $field) {
                            if (isset($field['id']) && isset($field['value'])) {
                                $row['' . $field['id']] = $field['value'];
                            }
                        }
                    } else {
                        // The newer format is a plain object of keys and values.
                        foreach ($fieldsArray as $key => $value) {
                            $row['' . $key] = $value;
                        }
                    }
                }
                unset($row['fields']);
            }
        }
        unset($row);

        foreach ($data as $i => $item) {
            foreach ($codeFields as $x) {
                if (isset($item[$x])) {
                    $data[$i][$x] = htmlspecialchars($item[$x]);
                }
            }
        }

        return $data;
    }

    /**
     * Answer one DataTables request for the saved versions of one entry.
     *
     * An entry has few versions, so they are all read, then filtered, sorted and paged here.
     *
     * @param array $post The POST values DataTables sent (id, draw, start, length, search, order, columns, find).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     * @throws \App\Data\LookupException When the entry id is missing or names no entry, or the entry names no model.
     */
    public function versionsDataTable(array $post): array
    {
        $entryId = $post['id'] ?? null;
        $draw = $post['draw'] ?? 1;
        $start = $post['start'] ?? 0;
        $length = $post['length'] ?? 10;
        $search = $post['search']['value'] ?? '';
        $order = $post['order'] ?? null;
        $columns = $post['columns'] ?? null;
        $find = $post['find'] ?? null;

        if (! $entryId) {
            throw new LookupException('No entry id provided.');
        }

        $versions = $this->reader()->revisions($entryId);

        if ($versions === []) {
            throw new LookupException("Entry with id $entryId not found.");
        }

        $modelId = $versions[0]['model_id'];
        $model   = $this->db()->table('hyper_models')->select('fields')->where('id', $modelId)->where('deleted_at', null)->get()->getRow();

        if (! $model) {
            throw new LookupException("Model with id $modelId not found.");
        }

        $types         = $this->fieldTypes($model->fields);
        $dateFields    = $types['dateFields'];
        $numericFields = $types['numericFields'];
        $codeFields    = $types['codeFields'];

        // Every version is read once, and its fields are decoded for the filters and the sort.
        $decoded = [];
        foreach ($versions as $i => $version) {
            $decoded[$i] = $this->decode($version['fields']);
        }
        $indexes = array_keys($versions);

        if (is_array($find) && ! empty($find['field']) && ! empty($find['value'])) {
            $indexes = array_values(array_filter($indexes, static fn (int $i): bool => FieldRules::findMatches($decoded[$i], (string) $find['field'], (string) $find['value'])));
        }

        // The total counts this entry's versions after the find filter, before the search.
        $totalRecords = count($indexes);

        if (! empty($search)) {
            $term    = esc($search);
            $indexes = array_values(array_filter($indexes, static fn (int $i): bool => FieldRules::contains((string) ($versions[$i]['fields'] ?? ''), $term)));
        }

        $recordsFiltered = count($indexes);

        $keys = [];
        if (! empty($order)) {
            $orderColumnIndex = $order[0]['column'];
            $orderDir         = $order[0]['dir'];
            $orderColumn      = $columns[$orderColumnIndex]['data']; // The field key for dynamic fields.

            if (in_array($orderColumn, EntryDataStaticFields::FIELD_LIST)) {
                if ($orderColumn === EntryDataStaticFields::CREATED_BY) {
                    foreach ($indexes as $i) {
                        $keys[$i] = $versions[$i]['created_by'] === null ? null : FieldRules::sortFold((string) $versions[$i]['created_by']);
                    }
                } elseif ($orderColumn === EntryDataStaticFields::DATE_CREATED) {
                    foreach ($indexes as $i) {
                        $keys[$i] = (string) $versions[$i]['date_created'];
                    }
                } else {
                    // A version has no deleter and no deletion date of its own, and the list of versions never had these columns.
                    throw new DatabaseException("Unknown column '$orderColumn' in 'order clause'", 1054);
                }
            } else {
                $kind = in_array($orderColumn, $dateFields) ? 'date' : (in_array($orderColumn, $numericFields) ? 'number' : 'text');
                foreach ($indexes as $i) {
                    $keys[$i] = FieldRules::sortKey($kind, $decoded[$i], (string) $orderColumn);
                }
            }
            $descending = $this->direction($orderDir) === 'DESC';
        } else {
            foreach ($indexes as $i) {
                $keys[$i] = (string) $versions[$i]['date_created'];
            }
            $descending = true;
        }

        usort($indexes, static function (int $a, int $b) use ($keys, $versions, $descending): int {
            $cmp = FieldRules::compare($keys[$a], $keys[$b]);

            return ($descending ? -$cmp : $cmp) ?: (int) $versions[$a]['id'] <=> (int) $versions[$b]['id'];
        });

        $rows = array_map(static fn (int $i): array => $versions[$i], $indexes);

        // A length of -1 means no limit.
        if ($length != -1 && intval($length) !== 0) {
            $rows = array_slice($rows, intval($start), intval($length));
        }

        return [
            'draw'            => intval($draw),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $this->pivotRows($rows, $codeFields),
        ];
    }

    /**
     * Read one page of entries for the token API.
     *
     * @param array $filters The optional `model_id`, `search` and `hidden_model_ids`.
     * @param string $sortColumn A column from the caller's own fixed list, never text from a request.
     * @param string $direction ASC or DESC.
     * @param int $limit Entries per page.
     * @param int $page The page number, starting at 1.
     * @return array The rows under `data` and the count of all matching entries under `total`.
     */
    public function page(array $filters, string $sortColumn, string $direction, int $limit, int $page): array
    {
        $builder = $this->rows()->query();

        $modelId = $filters['model_id'] ?? null;
        if ($modelId !== null && $modelId !== '') {
            $builder->where('e.model_id', (int) $modelId);
        }

        if (! empty($filters['hidden_model_ids'])) {
            $builder->whereNotIn('e.model_id', $filters['hidden_model_ids']);
        }

        // The search covers the model name and the creator's username only, so it stays in SQL.
        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $builder->groupStart()
                ->like('m.name', $search)
                ->orLike($this->rows()->column('created_by'), $search)
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);

        $data = $builder
            ->orderBy($this->rows()->column($sortColumn), $direction)
            ->orderBy('e.id', 'ASC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->getResultArray();

        return ['data' => $this->rows()->hydrate($data), 'total' => $total];
    }

    /**
     * Apply the filters every list of entries shares: one id, a list of ids, one model, and the models to leave out.
     */
    private function scoped(BaseBuilder $builder, mixed $id, mixed $ids, mixed $modelId, array $hiddenModelIds): BaseBuilder
    {
        if (! empty($id)) {
            $builder->where('e.id', $id);
        }

        if (! empty($ids)) {
            $builder->whereIn('e.id', $ids);
        }

        if (! empty($modelId)) {
            $builder->where('e.model_id', $modelId);
        }

        if (! empty($hiddenModelIds)) {
            $builder->whereNotIn('e.model_id', $hiddenModelIds);
        }

        return $builder;
    }

    /**
     * Drop from a saved list what this request's own filters leave out.
     *
     * @param list<array{0: int, 1: int}> $rows
     * @return list<array{0: int, 1: int}>
     */
    private function dropFiltered(array $rows, mixed $id, mixed $ids, array $hiddenModelIds): array
    {
        $hidden = array_flip(array_map('intval', $hiddenModelIds));
        $only   = empty($ids) ? null : array_flip(array_map('intval', (array) $ids));

        return array_values(array_filter($rows, static function (array $row) use ($id, $only, $hidden): bool {
            return ! isset($hidden[$row[1]]) && (empty($id) || $row[0] === (int) $id) && ($only === null || isset($only[$row[0]]));
        }));
    }

    /**
     * Take one page of a saved list and read its rows.
     *
     * @param list<array{0: int, 1: int}> $rows
     */
    private function pageRows(array $rows, mixed $start, mixed $length, bool $trashed): array
    {
        // A length of -1 means no limit, and a length of 0 has always meant the same.
        if ($length != -1 && intval($length) !== 0) {
            $rows = array_slice($rows, max(0, intval($start)), intval($length));
        }

        return $this->fetchRows(array_map(static fn (array $row): int => $row[0], $rows), $trashed);
    }

    /**
     * Read the rows of these entries in the order given.
     *
     * @param list<int> $ids
     *
     * @return list<array>
     */
    private function fetchRows(array $ids, bool $trashed): array
    {
        $byId = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach ($this->rows()->query($trashed)->whereIn('e.id', $chunk)->get()->getResultArray() as $row) {
                $byId[(int) $row['id']] = $row;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $this->rows()->hydrate($ordered, $trashed);
    }

    /**
     * Read the fields of a chunk of live candidates from StarDust.
     *
     * @return array<int, array{fields: array}>
     */
    private function readLive(array $chunk): array
    {
        $read = [];
        foreach ($this->payloads()->read($chunk) as $id => $fields) {
            $read[$id] = ['fields' => $fields];
        }

        return $read;
    }

    /**
     * Read the snapshots of a chunk of trashed candidates, which HyperCMS keeps.
     *
     * @return array<int, array{fields: array, text: string}>
     */
    private function readSnapshots(array $chunk): array
    {
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $chunk);

        $read = [];
        foreach ($this->db()->table('hyper_entries AS e')
            ->select('e.id, r.fields')
            ->join('hyper_entry_revisions AS r', 'e.current_revision_id = r.id')
            ->whereIn('e.id', $ids)
            ->get()
            ->getResultArray() as $row) {
            $read[(int) $row['id']] = ['fields' => $this->decode($row['fields']), 'text' => (string) $row['fields']];
        }

        return $read;
    }

    /**
     * The fields of stored text as a plain array. The older list format `[{"id":"title","value":"x"}]` becomes an
     * object, and anything that is not a set of fields is an empty one.
     */
    private function decode(?string $text): array
    {
        $data = json_decode((string) $text, true);
        if (! is_array($data)) {
            return [];
        }

        if ($data !== [] && array_is_list($data) && isset($data[0]['id'])) {
            $fields = [];
            foreach ($data as $field) {
                if (isset($field['id']) && isset($field['value'])) {
                    $fields['' . $field['id']] = $field['value'];
                }
            }

            return $fields;
        }

        return array_is_list($data) ? [] : $data;
    }

    /**
     * ASC or DESC from whatever the request sent, the way the query builder reads it. Anything else is ascending.
     */
    private function direction(mixed $direction): string
    {
        return strtoupper(trim((string) $direction)) === 'DESC' ? 'DESC' : 'ASC';
    }

    private function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }

    private function rows(): EntryRows
    {
        return $this->rows ??= new EntryRows($this->db(), $this->payloads());
    }

    private function reader(): EntryReader
    {
        return $this->reader ??= new EntryReader($this->db(), $this->rows(), $this->payloads());
    }

    private function payloads(): EntryPayloads
    {
        return $this->payloads ??= new EntryPayloads();
    }

    private function lists(): SavedLists
    {
        return $this->lists ??= new SavedLists();
    }

    private function versions(): WriteVersions
    {
        return $this->versions ??= new WriteVersions($this->db());
    }

    private function scan(): ListScan
    {
        return $this->scan ??= new ListScan($this->payloads());
    }

    private function indexedReads(): IndexedReads
    {
        return $this->indexed ??= new IndexedReads(null, $this->db());
    }
}
