<?php

namespace App\Data;

use App\Libraries\SortColumn;
use StarDust\Models\EntriesModel;

/**
 * The one place that reads and writes entries.
 *
 * Controllers call this class and never touch StarDust themselves, so the engine
 * behind it can change without editing every controller.
 */
class EntryStore
{
    public function __construct(private EntriesModel $entries)
    {
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
}
