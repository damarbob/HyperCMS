<?php

namespace App\Controllers\API\v1;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

/**
 * Read-only REST access to entries.
 *
 * The routes in `Routes.php` put this behind the `tokens` filter, so a request needs a valid
 * `Authorization: Bearer <token>` header. The token must also carry the `entries.read` scope,
 * and its user must be in a group that may open the admin entries pages.
 * Writing entries is not supported here yet.
 */
class EntriesResource extends ResourceController
{
    protected $format = 'json';

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
     * Groups that may read entries. These match the `group` filter on the admin entries routes.
     */
    private const READER_GROUPS = ['superadmin', 'admin', 'developer'];

    /**
     * List entries, newest edit first by default.
     *
     * Query parameters: `limit` (1 to 100, default 20), `page`, `sort`, `order`,
     * `q` (matches the model name or the creator's username) and `model_id`.
     */
    public function index()
    {
        if ($denied = $this->denyUnlessAllowed()) {
            return $denied;
        }

        // Read the query string once and drop values that are not plain strings, such as `sort[]=id`.
        // Validate this same array and take every value from it, so what is checked is what is used.
        $params = array_filter($this->request->getGet() ?? [], 'is_string');

        $rules = [
            'limit'    => 'if_exist|integer|greater_than[0]|less_than_equal_to[100]',
            'page'     => 'if_exist|integer|greater_than[0]',
            'sort'     => 'if_exist|in_list[' . implode(',', array_keys(self::SORTABLE)) . ']',
            'order'    => 'if_exist|in_list[ASC,DESC,asc,desc]',
            'model_id' => 'if_exist|integer',
        ];

        if (! $this->validateData($params, $rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $limit   = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $page    = max(1, (int) ($params['page'] ?? 1));
        $sort    = array_key_exists($params['sort'] ?? '', self::SORTABLE) ? $params['sort'] : 'updated_at';
        $order   = strtoupper($params['order'] ?? '') === 'ASC' ? 'ASC' : 'DESC';
        $search  = $params['q'] ?? null;
        $modelId = $params['model_id'] ?? null;

        // The sort column comes from the fixed list above, never from the request text.
        $result = service('entryStore')->page(
            ['model_id' => $modelId, 'search' => $search],
            self::SORTABLE[$sort],
            $order,
            $limit,
            $page,
        );
        $total = $result['total'];

        return $this->respond([
            'data'  => $result['data'],
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
        if ($denied = $this->denyUnlessAllowed()) {
            return $denied;
        }

        $entry = service('entryStore')->findRow($id);

        if (! $entry) {
            return $this->failNotFound('Entry not found.');
        }

        return $this->respond($entry);
    }

    /**
     * Return a 403 response unless the token has the `entries.read` scope
     * and its user is in one of the reader groups.
     */
    private function denyUnlessAllowed(): ?ResponseInterface
    {
        $user = auth('tokens')->user();

        if ($user === null || $user->tokenCant('entries.read') || ! $user->inGroup(...self::READER_GROUPS)) {
            return $this->failForbidden('This account or token cannot read entries.');
        }

        return null;
    }
}
