<?php

namespace App\Data\Entries;

use App\Data\WriteVersions;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\BaseConnection;
use StarDust\Filter\Ast\FilterNode;
use StarDust\Read\Cursor;
use StarDust\Read\Entry;
use StarDust\Read\EntryPage;
use StarDust\Read\EntryQuery;
use StarDust\Read\SortSpec;
use StarDust\StarDust;

/**
 * Reads the ids of a model's entries through StarDust's index, for a sort or a filter on a filterable field.
 *
 * StarDust pages with a cursor and has no offset, and the admin lists page with an offset. A page at a given offset
 * is reached by reading from the start, `WALK` entries at a time, so the cost of a deep page grows with its offset.
 * StarDust returns the whole stored payload of every entry a read returns, even when no field is asked for, so a read
 * is kept small to keep memory small, because bodies of 1 to 3 MB exist. The cursor at the end of each read is kept in
 * the cache under the model's write version, and the next walk of the same list starts from the nearest one.
 * The fields of the page come from `getMany()` afterwards, so every list returns the same row it always did, with
 * every stored key and not only the registered ones.
 */
class IndexedReads
{
    /**
     * The entries one read of a walk returns. A cursor is kept at every multiple of it.
     */
    private const WALK = 100;

    public function __construct(
        private ?StarDust $engine = null,
        private ?BaseConnection $db = null,
        private ?WriteVersions $versions = null,
        private ?CacheInterface $cache = null,
    ) {
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

        if ($bound > 0 && $need !== null && $need > $bound) {
            throw new ScanLimitExceeded($need, $bound);
        }

        // A page that fits in one read needs no walk and no cursor.
        if ($need !== null && $need <= self::WALK) {
            $page = $this->read($sdId, $sort, $filter, max(1, $need), null);

            return ['ids' => array_slice($this->ids($page->rows), $offset, $limit), 'more' => $page->nextCursor !== null];
        }

        // Start from the nearest cursor kept for this list at or before the offset.
        $key     = $this->key($modelId, $sdId, $sort, $filter);
        $cursors = $this->cache()->get($key);
        $cursors = is_array($cursors) ? $cursors : [];
        $block   = intdiv($offset, self::WALK);
        while ($block > 0 && ! isset($cursors[$block])) {
            $block--;
        }

        $at     = $block * self::WALK;
        $cursor = $block > 0 ? new Cursor($cursors[$block]) : null;
        $ids    = [];
        $kept   = false;

        do {
            $page = $this->read($sdId, $sort, $filter, self::WALK, $cursor);
            array_push($ids, ...$this->ids($page->rows));
            $at    += count($page->rows);
            $cursor = $page->nextCursor;

            if ($cursor !== null && $at % self::WALK === 0 && ! isset($cursors[intdiv($at, self::WALK)])) {
                $cursors[intdiv($at, self::WALK)] = $cursor->opaque;
                $kept = true;
            }
            if ($bound > 0 && $at > $bound) {
                throw new ScanLimitExceeded($at, $bound);
            }
        } while ($cursor !== null && ($need === null || $at < $need));

        if ($kept) {
            $this->cache()->save($key, $cursors, max(1, (int) config('HyperEngine')->savedListExpiry));
        }

        $more = $cursor !== null || ($need !== null && $at > $need);

        return ['ids' => array_slice($ids, $offset - $block * self::WALK, $limit), 'more' => $more];
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
        $size   = max(1, min(self::WALK, $cap + 1));

        do {
            $page = $this->read($sdId, null, $filter, $size, $cursor);
            $total += count($page->rows);
            $cursor = $page->nextCursor;
        } while ($cursor !== null && $total <= $cap);

        return $cursor !== null ? null : $total;
    }

    private function read(int $sdId, ?SortSpec $sort, ?FilterNode $filter, int $size, ?Cursor $cursor): EntryPage
    {
        return $this->engine()->read(new EntryQuery(
            tenantId: $this->tenant(),
            modelId: $sdId,
            filter: $filter,
            selectFields: [],
            pageSize: $size,
            cursor: $cursor,
            sort: $sort,
        ));
    }

    /**
     * @param list<Entry> $rows
     *
     * @return list<int>
     */
    private function ids(array $rows): array
    {
        return array_map(static fn (Entry $entry): int => $entry->id, $rows);
    }

    /**
     * The cache key of one list. It carries the model's write version, so a save starts the list again.
     */
    private function key(int $modelId, int $sdId, ?SortSpec $sort, ?FilterNode $filter): string
    {
        return 'hyper_cursors_' . sha1(json_encode([
            $sdId,
            $sort?->keyIdentity(),
            $sort?->direction->value,
            $sort?->effectiveNulls()->value,
            $filter === null ? null : serialize($filter),
            $this->versions()->forModel($modelId),
        ]));
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

    private function cache(): CacheInterface
    {
        return $this->cache ??= service('cache');
    }

    private function versions(): WriteVersions
    {
        return $this->versions ??= new WriteVersions($this->db());
    }

    private function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }
}
