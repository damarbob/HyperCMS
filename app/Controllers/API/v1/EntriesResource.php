<?php

namespace App\Controllers\API\v1;

use CodeIgniter\RESTful\ResourceController;

/**
 * Read-only REST access to entries.
 *
 * The routes in `Routes.php` put this behind the `tokens` filter, so a request needs a valid
 * `Authorization: Bearer <token>` header. Writing entries is not supported here yet.
 */
class EntriesResource extends ResourceController
{
    protected $modelName = 'StarDust\Models\EntriesModel';
    protected $format    = 'json';

    /**
     * Sort keys a client may use, mapped to the columns they stand for.
     */
    private const SORTABLE = [
        'id'         => 'entries.id',
        'created_at' => 'entries.created_at',
        'updated_at' => 'entry_data.created_at',
        'model_name' => 'model_data.name',
    ];

    /**
     * List entries, newest edit first by default.
     *
     * Query parameters: `limit` (1 to 100, default 20), `page`, `sort`, `order`,
     * `q` (matches the model name or the creator's username) and `model_id`.
     */
    public function index()
    {
        $rules = [
            'limit'    => 'if_exist|integer|greater_than[0]|less_than_equal_to[100]',
            'page'     => 'if_exist|integer|greater_than[0]',
            'sort'     => 'if_exist|in_list[' . implode(',', array_keys(self::SORTABLE)) . ']',
            'order'    => 'if_exist|in_list[ASC,DESC,asc,desc]',
            'model_id' => 'if_exist|integer',
        ];

        if (! $this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $limit   = (int) ($this->request->getGet('limit') ?? 20);
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $sort    = $this->request->getGet('sort') ?? 'updated_at';
        $order   = strtoupper($this->request->getGet('order') ?? 'DESC');
        $search  = $this->request->getGet('q');
        $modelId = $this->request->getGet('model_id');

        /** @var \StarDust\Models\EntriesModel $model */
        $model   = $this->model;
        $builder = $model->stardust();

        if ($modelId !== null && $modelId !== '') {
            $builder->where('entries.model_id', (int) $modelId);
        }

        if (is_string($search) && $search !== '') {
            $builder->groupStart()
                ->like('model_data.name', $search)
                ->orLike('users.username', $search)
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);

        $data = $builder
            ->orderBy(self::SORTABLE[$sort], $order)
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->getResultArray();

        return $this->respond([
            'data'  => $data,
            'pager' => [
                'current_page' => $page,
                'total_pages'  => (int) ceil($total / $limit),
                'total_items'  => $total,
                'per_page'     => $limit,
            ],
        ]);
    }

    /**
     * Show one entry.
     */
    public function show($id = null)
    {
        /** @var \StarDust\Models\EntriesModel $model */
        $model = $this->model;

        $entry = $model->stardust()->where('entries.id', $id)->get()->getRowArray();

        if (! $entry) {
            return $this->failNotFound('Entry not found.');
        }

        return $this->respond($entry);
    }
}
