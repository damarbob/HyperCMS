<?php

namespace App\Data;

use App\Libraries\SortColumn;
use StarDust\Models\ModelsModel;

/**
 * The one place that reads and writes models.
 *
 * Controllers call this class and never touch StarDust themselves, so the engine
 * behind it can change without editing every controller.
 */
class ModelStore
{
    public function __construct(private ModelsModel $models)
    {
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
}
