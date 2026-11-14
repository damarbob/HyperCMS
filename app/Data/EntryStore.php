<?php

namespace App\Data;

use App\Constants\EntryDataStaticFields;
use App\Constants\ModelStaticFields;
use App\Libraries\SortColumn;
use StarDust\Models\EntriesModel;
use StarDust\Models\EntryDataModel;
use StarDust\Services\EntriesManager;

/**
 * The one place that reads and writes entries.
 *
 * Controllers call this class and never touch StarDust themselves, so the engine
 * behind it can change without editing every controller.
 */
class EntryStore
{
    public function __construct(
        private EntriesModel $entries,
        private EntryDataModel $entryData,
        private ModelStore $models,
        private EntriesManager $manager,
    ) {
    }

    /**
     * Read one live entry as a row with StarDust's own column names, or false when there is none.
     */
    public function findArray(int $id): array|false
    {
        return $this->manager->find($id);
    }

    /**
     * Create an entry and return its id.
     */
    public function create(array $data, int $userId): int
    {
        return $this->manager->create($data, $userId);
    }

    /**
     * Save a new version of an entry.
     */
    public function update(int $id, array $data, int $userId): void
    {
        $this->manager->update($id, $data, $userId);
    }

    /**
     * Move entries to the trash.
     */
    public function trash(array $ids, int $userId): void
    {
        $this->manager->deleteEntries($ids, $userId);
    }

    /**
     * Read the trashed entries among the given ids, or false when there are none.
     */
    public function trashed(array $ids): array|false
    {
        return $this->manager->findDeletedEntries($ids);
    }

    /**
     * Count the entries in the trash.
     */
    public function countTrashed(): int|string
    {
        return $this->manager->countDeleted();
    }

    /**
     * Delete the trashed entries for good.
     */
    public function emptyTrash(): void
    {
        $this->manager->purgeDeleted();
    }

    /**
     * Bring entries back from the trash.
     */
    public function restore(array $ids): void
    {
        $this->manager->restore($ids);
    }

    /**
     * Compile the SQL that reads every live entry as a flat table, without running it.
     *
     * The syntax processor wraps this SQL as a subquery so a form field can list entries.
     */
    public function compiledSelect(): string
    {
        return $this->entries->stardust()->getCompiledSelect();
    }

    /**
     * Answer one DataTables request for entries across all models.
     *
     * @param array $post The POST values DataTables sent (id, ids, model_id, draw, start, length, search, order, columns, trash).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     */
    public function dataTable(array $post): array
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

        // The trash view lists deleted entries.
        if (!$trash || $trash == 'false') {
            $builder = $this->entries->stardust()->withLegacyAliases(true);
        } else {
            $builder = $this->entries->stardust(true)->withLegacyAliases(true);
        }

        if (!empty($id)) {
            $builder->where('id', $id);
        }

        if (!empty($ids)) {
            $builder->whereIn('id', $ids);
        }

        if (!empty($modelId)) {
            $builder->where('model_id', $modelId);
        }

        $totalRecords = $builder->countAllResults(false);

        if (!empty($search)) {
            $builder->like('model_name', esc($search));
            $builder->orLike('fields', esc($search));
            $builder->orLike('created_by', esc($search));
        }

        $recordsFiltered = $builder->countAllResults(false);

        // DataTables sends the column index and direction, and the columns array names the
        // column. Only a plain column name may reach the query.
        $orderColumn = !empty($order) ? ($columns[$order[0]['column'] ?? 0]['data'] ?? null) : null;
        if (SortColumn::isSafe($orderColumn)) {
            $builder->orderBy($orderColumn, $order[0]['dir'] ?? '');
        } else {
            $builder->orderBy('date_modified', 'DESC');
        }

        // A length of -1 means no limit.
        if ($length != -1) {
            $builder->limit(intval($length), intval($start));
        }

        return [
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $builder->get()->getResultArray(),
        ];
    }

    /**
     * Read one live entry with the legacy column names, or null when there is none.
     *
     * This is the row the admin pages use. `findRow()` is the token API's version of it.
     */
    public function findAliased(int|string $id): ?array
    {
        return $this->entries->stardust()->withLegacyAliases(true)
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->getResultArray()[0] ?? null;
    }

    /**
     * Read the saved versions of one entry as rows, leaving out the first row of the query.
     */
    public function versionsOf(int|string $entryId): array
    {
        return $this->entryData->stardust()->withLegacyAliases(true)
            ->where('entry_id', $entryId)
            ->offset(1)
            ->get()
            ->getResultArray();
    }

    /**
     * Delete every saved version of an entry except the newest, for good.
     *
     * @return int|null The number of versions deleted, or null when the entry has no versions.
     */
    public function clearHistory(int|string $entryId): ?int
    {
        $newest = $this->entryData
            ->select('id')
            ->where('entry_id', $entryId)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        if (empty($newest)) {
            return null;
        }

        $this->entryData
            ->where('entry_id', $entryId)
            ->where('id !=', $newest->id)
            ->delete(purge: true);

        return $this->entryData->db->affectedRows();
    }

    /**
     * Count the live entries of one model.
     */
    public function countForModel(int|string $modelId): int
    {
        return $this->entries->stardust()->withLegacyAliases(true)
            ->where('model_id', $modelId)
            ->countAllResults();
    }

    /**
     * Read one page of entries for the token API.
     *
     * @param array $filters The optional `model_id`, and `search` text that matches the model name or the creator's username.
     * @param string $sortColumn A column from the caller's own fixed list, never text from a request.
     * @param string $direction ASC or DESC.
     * @param int $limit Entries per page.
     * @param int $page The page number, starting at 1.
     * @return array The rows under `data` and the count of all matching entries under `total`.
     */
    public function page(array $filters, string $sortColumn, string $direction, int $limit, int $page): array
    {
        $builder = $this->entries->stardust();

        $modelId = $filters['model_id'] ?? null;
        if ($modelId !== null && $modelId !== '') {
            $builder->where('entries.model_id', (int) $modelId);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $builder->groupStart()
                ->like('model_data.name', $search)
                ->orLike('users.username', $search)
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);

        $data = $builder
            ->orderBy($sortColumn, $direction)
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->getResultArray();

        return ['data' => $data, 'total' => $total];
    }

    /**
     * Read one entry by its id, or null when there is none.
     */
    public function findRow($id): ?array
    {
        return $this->entries->stardust()->where('entries.id', $id)->get()->getRowArray() ?: null;
    }

    /**
     * Answer one DataTables request for the entries of a single model.
     *
     * The caller may be a controller or another module that passes the values directly.
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

        if (!$modelId) {
            throw new \InvalidArgumentException("No model id provided.");
        }

        $modelInfo = $this->modelInfo($modelId);
        $dateFields = $modelInfo['dateFields'];
        $numericFields = $modelInfo['numericFields'];
        $codeFields = $modelInfo['codeFields'];

        $builder = $this->modelQuery($modelId, !$trash || $trash == 'false');

        $this->applyFind($builder, $find);

        // Count after the find filter and before the search, as the list screens expect.
        $totalRecords = $builder->countAllResults(false);

        $this->applySearch($builder, esc($search));

        $recordsFiltered = $builder->countAllResults(false);

        $this->applyModelOrdering($builder, $order, $columns, $dateFields, $numericFields);

        $this->applyPagination($builder, $length, $start);

        $data = $this->pivotRows($builder->get()->getResultArray(), $codeFields);

        return [
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    /**
     * Read the model and sort its fields by type.
     */
    protected function modelInfo(int $modelId): array
    {
        $model = $this->models->find($modelId);

        if (!$model) {
            throw new \InvalidArgumentException("Model with id $modelId not found.");
        }

        $modelFields = json_decode($model->fields);
        $dateFields = [];
        $numericFields = [];
        $codeFields = [];

        foreach ($modelFields as $field) {
            if (!empty($field->type)) {
                if ($field->type == 'datetime-local') {
                    $dateFields[] = $field->id;
                } elseif ($field->type == 'number') {
                    $numericFields[] = $field->id;
                } elseif ($field->type == 'code') {
                    $codeFields[] = $field->id;
                }
            }
        }

        return [
            'model' => $model,
            'dateFields' => $dateFields,
            'numericFields' => $numericFields,
            'codeFields' => $codeFields,
        ];
    }

    /**
     * Build the base query for the entries of one model.
     *
     * The flag is true for live entries and false for the trash view.
     */
    protected function modelQuery(int $modelId, bool $live)
    {
        if ($live) {
            $builder = $this->entries->stardust()->withLegacyAliases(true);
        } else {
            $builder = $this->entries->stardust(true)->withLegacyAliases(true);
        }

        $builder->where('model_id', $modelId);

        return $builder;
    }

    /**
     * Apply the find filter if one was sent.
     *
     * @todo Handle several find conditions, which `likeFields()` already supports.
     */
    protected function applyFind(&$builder, ?array $find): void
    {
        if ($find && !empty($find['field']) && !empty($find['value'])) {
            $builder->likeFields([$find]);
        }
    }

    /**
     * Apply the search filter if one was sent.
     */
    protected function applySearch(&$builder, string $search): void
    {
        if (!empty($search)) {
            $builder->where("LOWER(CAST(entry_data.fields AS CHAR)) LIKE '%" . strtolower($search) . "%'");
        }
    }

    /**
     * Apply the requested order. A dynamic field id is quoted and escaped before it
     * goes into the sort expression.
     */
    protected function applyModelOrdering(&$builder, ?array $order, ?array $columns, array $dateFields, array $numericFields): void
    {
        if (!empty($order)) {
            $orderColumnIndex = $order[0]['column'];
            $orderDir = $order[0]['dir'];
            $orderColumn = $columns[$orderColumnIndex]['data'];

            $quoted = db_connect()->escape((string) $orderColumn);

            if (in_array($orderColumn, ModelStaticFields::FIELD_LIST)) {
                $builder->orderBy($orderColumn, $orderDir);
            } else {
                if (in_array($orderColumn, $dateFields)) {
                    $orderExpr = "STR_TO_DATE( JSON_UNQUOTE( JSON_EXTRACT( entry_data.fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(entry_data.fields, 'one', " . $quoted . ", NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ), '%Y-%m-%d %H:%i:%s' )";
                    $builder->orderBy($orderExpr, $orderDir, false);
                } elseif (in_array($orderColumn, $numericFields)) {
                    $orderExpr = "CAST( JSON_UNQUOTE( JSON_EXTRACT( entry_data.fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(entry_data.fields, 'one', " . $quoted . ", NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ) AS DECIMAL(10,2) )";
                    $builder->orderBy($orderExpr, $orderDir, false);
                } else {
                    $orderExpr = "LOWER(TRIM(REGEXP_REPLACE( CAST(JSON_EXTRACT(entry_data.fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(entry_data.fields, 'one', " . $quoted . ", NULL, '$[*].id'), '[', -1), ']', 1), '].value' )) AS CHAR), '<[^>]+>', '' )))";
                    $builder->orderBy($orderExpr, $orderDir, false);
                }
            }
        } else {
            $builder->orderBy('date_modified', 'DESC');
        }
    }

    /**
     * Apply pagination. A length of -1 means no limit.
     */
    protected function applyPagination(&$builder, int $length, int $start): void
    {
        if ($length != -1) {
            $builder->limit(intval($length), intval($start));
        }
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
                    if (array_is_list($fieldsArray) && !empty($fieldsArray) && isset($fieldsArray[0]['id'])) {
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
     * @param array $post The POST values DataTables sent (id, draw, start, length, search, order, columns, find).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     * @throws LookupException When the entry id is missing or names no entry, or the entry names no model.
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

        if (!$entryId) {
            throw new LookupException("No entry id provided.");
        }

        $latest = $this->entryData
            ->stardust()->withLegacyAliases(true)
            ->where('entry_id', $entryId)
            ->orderBy('date_created', 'DESC')
            ->get()
            ->getRow();

        if (!$latest) {
            throw new LookupException("Entry with id $entryId not found.");
        }

        $model = $this->models->find($latest->model_id);

        if (!$model) {
            throw new LookupException("Model with id $latest->model_id not found.");
        }

        $modelFields = json_decode($model->fields);
        $dateFields = [];
        $numericFields = [];
        $codeFields = [];

        foreach ($modelFields as $field) {
            if ($field->type == 'datetime-local') {
                $dateFields[] = $field->id;
            } elseif ($field->type == 'number') {
                $numericFields[] = $field->id;
            } elseif ($field->type == 'code') {
                $codeFields[] = $field->id;
            }
        }

        $builder = $this->entryData->stardust()->withLegacyAliases(true);

        if (!empty($entryId)) {
            $builder->where('entry_id', $entryId);
        }

        if ($find && !empty($find['field']) && !empty($find['value'])) {
            // The field id and the value go into a SQL string below, so escape both first.
            $db = db_connect();
            $builder->where("
                LOWER(
                    JSON_UNQUOTE(
                        JSON_EXTRACT(
                            entry_data.fields,
                            CONCAT(
                                '$[',
                                SUBSTRING_INDEX(
                                    SUBSTRING_INDEX(
                                        JSON_SEARCH(entry_data.fields, 'one', " . $db->escape((string) $find['field']) . ", NULL, '$[*].id'),
                                        '[',
                                        -1
                                    ),
                                    ']',
                                    1
                                ),
                                '].value'
                            )
                        )
                    )
                ) LIKE '%" . $db->escapeLikeString(strtolower((string) $find['value'])) . "%'
            ");
        }

        // The total counts this entry's versions after the find filter, before the search.
        $totalRecords = $builder->countAllResults(false);

        if (!empty($search)) {
            $builder->where("LOWER(CAST(entry_data.fields AS CHAR)) LIKE '%" . strtolower(esc($search)) . "%'");
        }

        $recordsFiltered = $builder->countAllResults(false);

        if (!empty($order)) {
            $orderColumnIndex = $order[0]['column'];
            $orderDir = $order[0]['dir'];
            $orderColumn = $columns[$orderColumnIndex]['data']; // The field key for dynamic fields.

            // The field id goes into a SQL string below, so quote and escape it first.
            $quoted = db_connect()->escape((string) $orderColumn);

            if (in_array($orderColumn, EntryDataStaticFields::FIELD_LIST)) {
                $builder->orderBy($orderColumn, $orderDir);
            } else {
                if (in_array($orderColumn, $dateFields)) {
                    $orderExpr = "STR_TO_DATE( JSON_UNQUOTE( JSON_EXTRACT( entry_data.fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(entry_data.fields, 'one', " . $quoted . ", NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ), '%Y-%m-%d %H:%i:%s' )";
                    $builder->orderBy($orderExpr, $orderDir, false);
                } elseif (in_array($orderColumn, $numericFields)) {
                    $orderExpr = "CAST( JSON_UNQUOTE( JSON_EXTRACT( entry_data.fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(entry_data.fields, 'one', " . $quoted . ", NULL, '$[*].id'), '[', -1), ']', 1), '].value' ) ) ) AS DECIMAL(10,2) )";
                    $builder->orderBy($orderExpr, $orderDir, false);
                } else {
                    $orderExpr = "LOWER(TRIM(REGEXP_REPLACE( CAST(JSON_EXTRACT(entry_data.fields, CONCAT( '$[', SUBSTRING_INDEX( SUBSTRING_INDEX(JSON_SEARCH(entry_data.fields, 'one', " . $quoted . ", NULL, '$[*].id'), '[', -1), ']', 1), '].value' )) AS CHAR), '<[^>]+>', '' )))";
                    $builder->orderBy($orderExpr, $orderDir, false);
                }
            }
        } else {
            $builder->orderBy('date_created', 'DESC');
        }

        // A length of -1 means no limit.
        if ($length != -1) {
            $builder->limit(intval($length), intval($start));
        }

        return [
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $this->pivotRows($builder->get()->getResultArray(), $codeFields),
        ];
    }
}
