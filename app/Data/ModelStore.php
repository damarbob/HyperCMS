<?php

namespace App\Data;

use App\Libraries\SortColumn;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use LogicException;

/**
 * The one place that reads and writes models.
 *
 * Controllers call this class and never touch StarDust themselves, so the engine behind it can
 * change without editing every controller. A model lives wholly in `hyper_models` and its saved
 * versions in `hyper_model_revisions`. StarDust 0.3 knows each model only as `m{id}` with the names
 * of its fields, so a model's labels, options and icons have no place there.
 */
class ModelStore
{
    public function __construct(private ?BaseConnection $db = null)
    {
    }

    /**
     * Read one live model as a row with StarDust's own column names, or false when there is none.
     *
     * The edit form uses this row. `find()` returns the row with the legacy names the lists use.
     */
    public function findArray(int $id): array|false
    {
        return $this->listing()->where('m.id', $id)->get()->getResultArray()[0] ?? false;
    }

    /**
     * Create a model and return its id.
     */
    public function create(array $data, int $userId): int
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Save a new version of a model.
     */
    public function update(int $id, array $data, int $userId): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Move models to the trash.
     */
    public function trash(array $ids, int $userId): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read the trashed models among the given ids, or false when there are none.
     */
    public function trashed(array $ids): array|false
    {
        if ($ids === []) {
            return false;
        }

        return $this->listing(true)->whereIn('m.id', $ids)->get()->getResultArray() ?: false;
    }

    /**
     * Count the models in the trash.
     */
    public function countTrashed(): int|string
    {
        return $this->listing(true)->countAllResults();
    }

    /**
     * Delete the trashed models for good.
     */
    public function emptyTrash(): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Bring models back from the trash.
     */
    public function restore(array $ids): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Delete every saved version of a model except the newest, for good.
     *
     * @return int|null The number of versions deleted, or null when the model has no versions.
     */
    public function clearHistory(int|string $modelId): ?int
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Tell whether a live model with this id exists. A trashed model does not count.
     */
    public function exists(int|string $id): bool
    {
        return $this->db()->table('hyper_models')->where('id', $id)->where('deleted_at', null)->countAllResults() > 0;
    }

    /**
     * Read the groups that may open each model, keyed by model id. Trashed models are included,
     * because their entries can still show up in a trash view.
     *
     * @return array<int, list<string>>
     */
    public function userGroups(): array
    {
        $map = [];
        foreach ([false, true] as $onlyTrashed) {
            foreach ($this->listing($onlyTrashed)->get()->getResultArray() as $row) {
                $groups = json_decode($row['user_groups'] ?? '[]', true);
                $map[(int) $row['id']] = is_array($groups) ? array_values($groups) : [];
            }
        }

        return $map;
    }

    /**
     * Compile the SQL that reads every live model as a flat table, without running it.
     *
     * The syntax processor wraps this SQL as a subquery so a form field can list models.
     */
    public function compiledSelect(): string
    {
        return $this->listing()->getCompiledSelect();
    }

    /**
     * Read every live model as rows with StarDust's own column names.
     */
    public function allArray(): array
    {
        return $this->listing()->get()->getResultArray();
    }

    /**
     * Read every live model as an array of rows.
     */
    public function all(): array
    {
        return $this->allArray();
    }

    /**
     * Read one live model, or null when there is none.
     */
    public function find(int|string $id): ?object
    {
        return $this->listing()->where('m.id', $id)->get()->getRow();
    }

    /**
     * Answer one DataTables request for the list of models.
     *
     * @param array $post The POST values DataTables sent (draw, start, length, search, order, columns, trash).
     * @param array $hiddenIds Models the caller may not read. They are left out of the rows and the counts.
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     */
    public function dataTable(array $post, array $hiddenIds = []): array
    {
        $draw = $post['draw'] ?? 1;
        $start = $post['start'] ?? null;
        $length = $post['length'] ?? -1;
        $search = $post['search']['value'] ?? '';
        $order = $post['order'] ?? null;
        $columns = $post['columns'] ?? null;
        $trash = $post['trash'] ?? false;

        // The trash view lists deleted models.
        $builder = $this->listing($trash && $trash != 'false');

        if (!empty($hiddenIds)) {
            $builder->whereNotIn('m.id', $hiddenIds);
        }

        $totalRecords = $builder->countAllResults(false);

        if (!empty($search)) {
            $builder->groupStart();
            $builder->like($this->column('name'), esc($search));
            $builder->orLike($this->column('fields'), esc($search));
            $builder->orLike($this->column('created_by'), esc($search));
            $builder->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        // DataTables sends the column index and direction, and the columns array names the
        // column. Only a plain column name may reach the query.
        $orderColumn = !empty($order) ? ($columns[$order[0]['column'] ?? 0]['data'] ?? null) : null;
        if (SortColumn::isSafe($orderColumn)) {
            $builder->orderBy($this->column($orderColumn), $order[0]['dir'] ?? '');
        } else {
            $builder->orderBy($this->column('date_modified'), 'DESC');
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
     * The versions of a trashed model are left out until it is restored.
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

        $builder = $this->versions();

        $totalRecords = $builder->countAllResults(false);

        if (!empty($modelId)) {
            $builder->where('m.id', $modelId);
        }

        if (!empty($search)) {
            $builder->groupStart();
            $builder->like('r.name', esc($search));
            $builder->orLike('r.fields', esc($search));
            $builder->orLike($this->usersColumn('c'), esc($search));
            $builder->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        $orderColumn = !empty($order) ? ($columns[$order[0]['column'] ?? 0]['data'] ?? null) : null;
        if (SortColumn::isSafe($orderColumn)) {
            $builder->orderBy($this->versionColumn($orderColumn), $order[0]['dir'] ?? '');
        } else {
            // Newest version first.
            $builder->orderBy('r.id', 'DESC');
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

    /**
     * Every live or every trashed model with the columns a list shows, under the names a list uses.
     *
     * `date_modified` is when the current version was saved, and `edited_by` is who saved it.
     */
    private function listing(bool $onlyTrashed = false): BaseBuilder
    {
        $users = $this->users();

        $builder = $this->db()->table('hyper_models AS m')
            ->select([
                'm.id',
                'm.created_at',
                'm.deleted_at AS date_deleted',
                'm.name',
                'm.fields',
                'm.group',
                'm.user_groups',
                'm.icon',
                'r.created_at AS date_modified',
                'm.current_revision_id AS data_id',
                'c.' . $users['name'] . ' AS created_by',
                'e.' . $users['name'] . ' AS edited_by',
                'd.' . $users['name'] . ' AS deleted_by',
            ])
            ->join('hyper_model_revisions AS r', 'm.current_revision_id = r.id', 'left')
            ->join($users['table'] . ' AS c', 'm.creator_id = c.' . $users['id'], 'left')
            ->join($users['table'] . ' AS e', 'r.creator_id = e.' . $users['id'], 'left')
            ->join($users['table'] . ' AS d', 'm.deleter_id = d.' . $users['id'], 'left');

        return $onlyTrashed ? $builder->where('m.deleted_at IS NOT NULL', null, false) : $builder->where('m.deleted_at', null);
    }

    /**
     * Every saved version of a live model, with who saved it.
     */
    private function versions(): BaseBuilder
    {
        $users = $this->users();

        return $this->db()->table('hyper_model_revisions AS r')
            ->select([
                'r.id AS data_id',
                'r.name',
                'r.fields',
                'r.icon',
                'r.created_at AS date_created',
                'm.id',
                'c.' . $users['name'] . ' AS created_by',
            ])
            ->join('hyper_models AS m', 'r.model_id = m.id', 'left')
            ->join($users['table'] . ' AS c', 'r.creator_id = c.' . $users['id'], 'left')
            ->where('m.deleted_at', null);
    }

    /**
     * The table, key column and name column of the people who save models.
     *
     * @return array{table: string, id: string, name: string}
     */
    private function users(): array
    {
        $engine = config('HyperEngine');

        return ['table' => $engine->usersTable, 'id' => $engine->usersIdColumn, 'name' => $engine->usersUsernameColumn];
    }

    private function usersColumn(string $alias): string
    {
        return $alias . '.' . $this->users()['name'];
    }

    /**
     * Map a list column to the column it reads, as the legacy list names did. A name this does not know is
     * passed on as written, so an unknown column is a database error as before.
     */
    private function column(string $name): string
    {
        return match ($name) {
            'id'            => 'm.id',
            'name'          => 'm.name',
            'fields'        => 'm.fields',
            'group'         => 'm.group',
            'user_groups'   => 'm.user_groups',
            'icon'          => 'm.icon',
            'created_at'    => 'm.created_at',
            'date_modified' => 'r.created_at',
            'data_id'       => 'm.current_revision_id',
            'date_deleted'  => 'm.deleted_at',
            'created_by'    => $this->usersColumn('c'),
            'edited_by'     => $this->usersColumn('e'),
            'deleted_by'    => $this->usersColumn('d'),
            default         => $name,
        };
    }

    /**
     * The same for the list of versions.
     */
    private function versionColumn(string $name): string
    {
        return match ($name) {
            'data_id'      => 'r.id',
            'id'           => 'm.id',
            'name'         => 'r.name',
            'fields'       => 'r.fields',
            'icon'         => 'r.icon',
            'group'        => 'r.group',
            'user_groups'  => 'r.user_groups',
            'created_by'   => $this->usersColumn('c'),
            'date_created' => 'r.created_at',
            default        => $name,
        };
    }

    private function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }

    private function notPorted(string $method): LogicException
    {
        return new LogicException(static::class . '::' . $method . '() is not ported to StarDust 0.3 yet.');
    }
}
