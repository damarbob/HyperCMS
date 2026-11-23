<?php

namespace App\Controllers\API\v1;

use App\Data\Entries\FieldFilters;
use App\Data\Entries\ScanLimitExceeded;
use App\Filters\ApiAuthFilter;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use InvalidArgumentException;
use StarDust\Filter\QueryFilterValidationException;
use StarDust\Read\SortDirection;
use StarDust\Read\SortSpec;
use Throwable;

/**
 * Read-only REST access to entries.
 *
 * The routes in `Routes.php` put this behind the `api-auth` filter, so a request needs a valid
 * `Authorization: Bearer <token>` header or a signed-in admin session. A token must carry the
 * `entries.read` scope, and the user must be in a group that may open the admin entries pages.
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
     * List entries, newest edit first by default.
     *
     * Query parameters: `limit` (1 to 100, default 20), `page`, `sort`, `order`,
     * `q` (matches the model name or the creator's username) and `model_id`.
     *
     * A model's indexed fields, the ones its definition marks `"filterable": true` once StarDust has indexed them, add
     * two more. `sort=field:name` orders by one, and `filter[name]=value` keeps the entries whose field equals the value.
     * `filter[name][op]=value` takes another operator, one of `eq`, `neq`, `lt`, `lte`, `gt`, `gte`, `prefix`, `in` (values
     * separated by commas), `is_null` and `is_not_null`. Both need `model_id`, and every condition must hold. Without
     * a `sort` the entries come newest created first, or oldest with `order=asc`. These cannot be combined with `q`.
     */
    public function index()
    {
        if ($denied = $this->denyUnlessAllowed()) {
            return $denied;
        }

        // Read the query string once and drop values that are not plain strings, such as `sort[]=id`.
        // Validate this same array and take every value from it, so what is checked is what is used.
        $query  = $this->request->getGet() ?? [];
        $params = array_filter($query, 'is_string');

        // A sort on a field is `field:name`, whose name is the field's own id and so is not in the fixed list.
        $fieldSort = isset($params['sort']) && str_starts_with($params['sort'], 'field:') ? substr($params['sort'], 6) : null;
        $filter    = is_array($query['filter'] ?? null) ? $query['filter'] : [];

        $rules = [
            'limit'    => 'if_exist|integer|greater_than[0]|less_than_equal_to[100]',
            'page'     => 'if_exist|integer|greater_than[0]',
            'sort'     => $fieldSort !== null ? 'if_exist' : 'if_exist|in_list[' . implode(',', array_keys(self::SORTABLE)) . ']',
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

        if ($fieldSort !== null || $filter !== []) {
            return $this->byIndexedFields($params, $fieldSort, $filter, $limit, $page, $order);
        }

        // The sort column comes from the fixed list above, never from the request text.
        try {
            $result = service('entryStore')->page(
                ['model_id' => $modelId, 'search' => $search, 'hidden_model_ids' => service('modelAccess')->hiddenIds(ApiAuthFilter::caller())],
                self::SORTABLE[$sort],
                $order,
                $limit,
                $page,
            );
        } catch (ScanLimitExceeded $e) {
            return $this->fail($e->getMessage(), 422);
        }
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
     * Answer a list that sorts or filters on indexed fields, which StarDust orders and matches itself.
     *
     * @param array<string, string> $params The validated string parameters.
     * @param array<mixed> $filter The `filter` parameter.
     */
    private function byIndexedFields(array $params, ?string $fieldSort, array $filter, int $limit, int $page, string $order)
    {
        $modelId = $params['model_id'] ?? null;
        if ($modelId === null || $modelId === '') {
            return $this->fail(lang('Filterable.needsModel'), 422);
        }
        if (($params['q'] ?? '') !== '') {
            return $this->fail(lang('Filterable.noSearchWithField'), 422);
        }

        $modelId = (int) $modelId;
        // A page number that large only asks for an offset the read would refuse, and would overflow the sum below.
        $page   = min($page, 1_000_000);
        $caller  = ApiAuthFilter::caller();
        $hidden  = service('modelAccess')->hiddenIds($caller);

        // A model this caller may not read is answered like one with no entries, as `model_id` always was.
        $store   = service('entryStore');
        $indexed = in_array($modelId, array_map('intval', $hidden), true) ? [] : $store->indexedFields($modelId);
        $dir     = $order === 'ASC' ? SortDirection::Asc : SortDirection::Desc;

        try {
            if ($fieldSort !== null) {
                if (! isset($indexed[$fieldSort])) {
                    return $this->fail(lang('Filterable.notIndexed', ['field' => $fieldSort, 'indexed' => $indexed === [] ? lang('Filterable.noIndexedFields') : implode(', ', array_keys($indexed))]), 422);
                }
                $sort = SortSpec::byField($fieldSort, $dir);
            } elseif (($params['sort'] ?? 'id') === 'created_at') {
                $sort = SortSpec::byCreatedAt($dir);
            } elseif (($params['sort'] ?? 'id') === 'id') {
                $sort = SortSpec::byId($dir);
            } else {
                return $this->fail(lang('Filterable.sortKeyWithField', ['sort' => $params['sort']]), 422);
            }

            $ast    = FieldFilters::parse($filter, $indexed);
            $result = $store->fieldPage($modelId, $hidden, $sort, $ast, $limit, $page, (int) config('HyperEngine')->filterCountLimit);
        } catch (ScanLimitExceeded $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (QueryFilterValidationException $e) {
            return $this->fail(lang('Filterable.filterRefused', ['reason' => $e->getMessage()]), 422);
        } catch (Throwable $e) {
            // StarDust refuses a field that stopped being indexed between the check above and the read.
            if (str_starts_with($e::class, 'StarDust\\Exception\\')) {
                return $this->fail(lang('Filterable.filterRefused', ['reason' => $e->getMessage()]), 422);
            }

            throw $e;
        }

        $total = $result['total'];

        return $this->respond([
            'data'  => $result['data'],
            'pager' => [
                'current_page' => $page,
                'total_pages'  => $total === null ? null : (int) ceil($total / $limit),
                'total_items'  => $total,
                'per_page'     => $limit,
                'has_more'     => $result['more'],
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

        // An entry of a model this caller may not read is answered like one that does not exist.
        if (! $entry || ! service('modelAccess')->canRead(ApiAuthFilter::caller(), $entry['model_id'])) {
            return $this->failNotFound('Entry not found.');
        }

        return $this->respond($entry);
    }

    /**
     * Return a 403 response unless the caller may use the API.
     * The filter has already checked a token's scope.
     */
    private function denyUnlessAllowed(): ?ResponseInterface
    {
        $user = ApiAuthFilter::caller();

        if ($user === null || ! service('permissions')->can($user, 'api.access')) {
            return $this->failForbidden('This account or token cannot read entries.');
        }

        return null;
    }
}
