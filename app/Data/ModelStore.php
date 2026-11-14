<?php

namespace App\Data;

use App\Libraries\SortColumn;
use StarDust\Models\ModelDataModel;
use StarDust\Models\ModelsModel;
use StarDust\Services\ModelsManager;

/**
 * The one place that reads and writes models.
 *
 * Controllers call this class and never touch StarDust themselves, so the engine
 * behind it can change without editing every controller.
 */
class ModelStore
{
    public function __construct(
        private ModelsModel $models,
        private ModelDataModel $modelData,
        private ModelsManager $manager,
    ) {
    }

    /**
     * Read one live model as a row with StarDust's own column names, or false when there is none.
     *
     * The edit form uses this row. `find()` returns the row with the legacy names the lists use.
     */
    public function findArray(int $id): array|false
    {
        return $this->manager->find($id);
    }

    /**
     * Create a model and return its id.
     */
    public function create(array $data, int $userId): int
    {
        return $this->manager->create($data, $userId);
    }

    /**
     * Save a new version of a model.
     */
    public function update(int $id, array $data, int $userId): void
    {
        $this->manager->update($id, $data, $userId);
    }

    /**
     * Move models to the trash.
     */
    public function trash(array $ids, int $userId): void
    {
        $this->manager->deleteModels($ids, $userId);
    }

    /**
     * Read the trashed models among the given ids.
     */
    public function trashed(array $ids): array|false
    {
        return $this->manager->findDeletedModels($ids);
    }

    /**
     * Count the models in the trash.
     */
    public function countTrashed(): int|string
    {
        return $this->manager->countDeleted();
    }

    /**
     * Delete the trashed models for good.
     */
    public function emptyTrash(): void
    {
        $this->manager->purgeDeleted();
    }

    /**
     * Bring models back from the trash.
     */
    public function restore(array $ids): void
    {
        $this->manager->restore($ids);
    }

    /**
     * Delete every saved version of a model except the newest, for good.
     *
     * @return int|null The number of versions deleted, or null when the model has no versions.
     */
    public function clearHistory(int|string $modelId): ?int
    {
        $newest = $this->modelData
            ->select('id')
            ->where('model_id', $modelId)
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        if (empty($newest)) {
            return null;
        }

        $this->modelData
            ->where('model_id', $modelId)
            ->where('id !=', $newest->id)
            ->delete(purge: true);

        return $this->modelData->db->affectedRows();
    }

    /**
     * Tell whether a live model with this id exists. A trashed model does not count.
     */
    public function exists(int|string $id): bool
    {
        return !empty($this->models->where('id', $id)->findAll());
    }

    /**
     * Compile the SQL that reads every live model as a flat table, without running it.
     *
     * The syntax processor wraps this SQL as a subquery so a form field can list models.
     */
    public function compiledSelect(): string
    {
        return $this->models->stardust()->getCompiledSelect();
    }

    /**
     * Read every live model as rows with StarDust's own column names.
     */
    public function allArray(): array
    {
        return $this->manager->get();
    }

    /**
     * Read every live model as an array of rows.
     */
    public function all(): array
    {
        return $this->models->stardust()->withLegacyAliases(true)->get()->getResultArray();
    }

    /**
     * Read one live model, or null when there is none.
     */
    public function find(int|string $id): ?object
    {
        return $this->models->stardust()->withLegacyAliases(true)->where('id', $id)->get()->getRow();
    }

    /**
     * Answer one DataTables request for the list of models.
     *
     * @param array $post The POST values DataTables sent (draw, start, length, search, order, columns, trash).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     */
    public function dataTable(array $post): array
    {
        $draw = $post['draw'] ?? 1;
        $start = $post['start'] ?? null;
        $length = $post['length'] ?? -1;
        $search = $post['search']['value'] ?? '';
        $order = $post['order'] ?? null;
        $columns = $post['columns'] ?? null;
        $trash = $post['trash'] ?? false;

        // The trash view lists deleted models.
        if (!$trash || $trash == 'false') {
            $builder = $this->models->stardust()->withLegacyAliases(true);
        } else {
            $builder = $this->models->stardust(true)->withLegacyAliases(true);
        }

        $totalRecords = $builder->countAllResults(false);

        if (!empty($search)) {
            $builder->groupStart();
            $builder->like('name', esc($search));
            $builder->orLike('fields', esc($search));
            $builder->orLike('created_by', esc($search));
            $builder->groupEnd();
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
     * Answer one DataTables request for the saved versions of models.
     *
     * The default order names a column that the select does not have, so a request
     * without an order fails with a database error, as it did before this class existed.
     *
     * @param array $post The POST values DataTables sent (id, draw, start, length, search, order, columns).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     */
    public function versionsDataTable(array $post): array
    {
        $modelId = $post['id'] ?? null;
        $draw = $post['draw'] ?? 1;
        $start = $post['start'] ?? null;
        $length = $post['length'] ?? -1;
        $search = $post['search']['value'] ?? '';
        $order = $post['order'] ?? null;
        $columns = $post['columns'] ?? null;

        $builder = $this->modelData->stardust()->withLegacyAliases(true);

        $totalRecords = $builder->countAllResults(false);

        if (!empty($modelId)) {
            $builder->where('id', $modelId);
        }

        if (!empty($search)) {
            $builder->groupStart();
            $builder->like('name', esc($search));
            $builder->orLike('fields', esc($search));
            $builder->orLike('created_by', esc($search));
            $builder->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        $orderColumn = !empty($order) ? ($columns[$order[0]['column'] ?? 0]['data'] ?? null) : null;
        if (SortColumn::isSafe($orderColumn)) {
            $builder->orderBy($orderColumn, $order[0]['dir'] ?? '');
        } else {
            $builder->orderBy('date_modified', 'DESC');
        }

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
}
