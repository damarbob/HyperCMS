<?php

namespace App\Data\Entries;

use RuntimeException;

/**
 * Thrown when a search, a find filter or a sort on a model field would have to read more entries than
 * `Config\HyperEngine::scanLimit` allows. The message is meant for the person using the list.
 */
class ScanLimitExceeded extends RuntimeException
{
    public function __construct(public readonly int $entries, public readonly int $limit)
    {
        parent::__construct(lang('EntryLists.scanLimit', ['entries' => $entries, 'limit' => $limit]));
    }
}
