<?php

namespace App\Data\Entries;

use CodeIgniter\Database\BaseConnection;
use StarDust\Filter\Ast\FilterNode;
use StarDust\Read\EntryQuery;
use StarDust\Read\SortSpec;
use StarDust\StarDust;

/**
 * Reads the ids of a model's entries through StarDust's index, for a sort or a filter on a filterable field.
 *
 * StarDust pages with a cursor and has no offset, and the admin lists page with an offset. A page at a given offset
 * is reached by reading ids from the start, a thousand at a time, so the cost of a deep page grows with its offset.
 * The reads take ids only. The fields of the page come from `getMany()` afterwards, so every list returns the same
 * row it always did, with every stored key and not only the registered ones.
 */
class IndexedReads
{
    /**
     * The most ids StarDust returns in one read.
     */
    private const PAGE = 1000;

    public function __construct(private ?StarDust $engine = null, private ?BaseConnection $db = null)
    {
    }

    /**
     * The fields of a model that can be sorted and filtered on right now, with the StarDust type of each.
     *
     * A field that is filterable but still being backfilled is not in it.
     *
     * @return array<string, string> By field id.
     */
    public function indexed(int $modelId): array
    {
        $row = $this->db()->table('hyper_models')->select('sd_model_id')->where('id', $modelId)->get()->getRow();
        if ($row === null || $row->sd_model_id === null) {
            return [];
        }

        $fields = [];
        foreach ($this->engine()->describeModel($this->tenant(), (int) $row->sd_model_id)?->indexedFields() ?? [] as $field) {
            $fields[$field->name] = $field->declaredType;
        }

        return $fields;
    }

    /**
     * One page of StarDust entry ids, in the order of the sort and among the entries the filter matches.
     *
     * @param int|null $limit The page size, or null for every id from the offset on.
     *
     * @return array{ids: list<int>, more: bool} `more` says whether StarDust has entries beyond this page.
     *
     * @throws ScanLimitExceeded When reaching the page would mean reading more ids than `HyperEngine::scanLimit` allows,
     *                           which a deep offset or no page size at all would do. The bound keeps a request from
     *                           walking and holding a whole model.
     */
    public function page(int $modelId, ?SortSpec $sort, ?FilterNode $filter, int $offset, ?int $limit): array
    {
        $sdId   = $this->sdModelId($modelId);
        $offset = max(0, $offset);
        $need   = $limit === null ? null : $offset + max(0, $limit);
        $bound  = max(0, (int) config('HyperEngine')->scanLimit);
        $ids    = [];
        $cursor = null;

        if ($bound > 0 && $need !== null && $need > $bound) {
            throw new ScanLimitExceeded($need, $bound);
        }

        do {
            // A cursor marks a position only, so every read of one walk asks for the same page size.
            $size = $need !== null && $need <= self::PAGE ? max(1, $need) : self::PAGE;
            $page = $this->engine()->read(new EntryQuery(
                tenantId: $this->tenant(),
                modelId: $sdId,
                filter: $filter,
                selectFields: [],
                pageSize: $size,
                cursor: $cursor,
                sort: $sort,
            ));
            foreach ($page->rows as $entry) {
                $ids[] = $entry->id;
            }
            if ($bound > 0 && count($ids) > $bound) {
                throw new ScanLimitExceeded(count($ids), $bound);
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null && ($need === null || count($ids) < $need));

        $more = $cursor !== null || ($need !== null && count($ids) > $need);

        return ['ids' => array_slice($ids, $offset, $limit), 'more' => $more];
    }

    /**
     * How many entries match the filter, counted by reading their ids.
     *
     * @param int $cap The most entries to count. StarDust has no count of its own, so a large match set is not walked to the end.
     *
     * @return int|null Null when more than `$cap` entries match.
     */
    public function count(int $modelId, ?FilterNode $filter, int $cap): ?int
    {
        $sdId   = $this->sdModelId($modelId);
        $total  = 0;
        $cursor = null;
        // One entry more than the cap is enough to know the cap was passed.
        $size   = max(1, min(self::PAGE, $cap + 1));

        do {
            $page = $this->engine()->read(new EntryQuery(
                tenantId: $this->tenant(),
                modelId: $sdId,
                filter: $filter,
                selectFields: [],
                pageSize: $size,
                cursor: $cursor,
            ));
            $total += count($page->rows);
            $cursor = $page->nextCursor;
        } while ($cursor !== null && $total <= $cap);

        return $cursor !== null ? null : $total;
    }

    private function sdModelId(int $modelId): int
    {
        return (int) $this->db()->table('hyper_models')->select('sd_model_id')->where('id', $modelId)->get()->getRow()->sd_model_id;
    }

    private function tenant(): int
    {
        return (int) config('HyperEngine')->tenantId;
    }

    private function engine(): StarDust
    {
        return $this->engine ??= service('stardust');
    }

    private function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }
}
