<?php

namespace App\Data\Entries;

use CodeIgniter\Cache\CacheInterface;
use Config\HyperEngine;

/**
 * Keeps the ordered ids of one search and sort in CodeIgniter's cache, so the next page of the same list does not
 * scan the entries again.
 *
 * The key is the list's own description, such as `model=1|q=apple|sort=title:asc|v=57`, hashed. It carries the write
 * version of the model, or the global one for a list across models, so the moment an entry is saved the key changes
 * and the next request builds a fresh list. The entry stays in the cache until it expires and is never deleted by hand.
 */
class SavedLists
{
    /**
     * Bump when the shape of a stored list changes, so an old file is ignored rather than misread.
     */
    private const FORMAT = 1;

    public function __construct(private ?CacheInterface $cache = null, private ?HyperEngine $config = null)
    {
    }

    /**
     * The list for this key, built and stored when the cache has none.
     *
     * @param list<int|string|null> $key The parts that describe the list.
     * @param callable(): array{rows: list<array{0: int, 1: int}>, total: int} $build
     *
     * @return array{rows: list<array{0: int, 1: int}>, total: int}
     */
    public function remember(array $key, callable $build): array
    {
        $name   = 'hyper_list_' . sha1(self::FORMAT . json_encode($key, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        $cache  = $this->cache ??= service('cache');
        $stored = $cache->get($name);

        if (is_array($stored) && isset($stored['rows'], $stored['total'])) {
            return $stored;
        }

        $list = $build();
        $cache->save($name, $list, max(1, ($this->config ??= config('HyperEngine'))->savedListExpiry));

        return $list;
    }
}
