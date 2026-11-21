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
        // The count covers entries of models the caller may not read, so it stays out of the message
        // and is kept as a property for the server log only.
        parent::__construct(lang('EntryLists.scanLimit', ['limit' => $limit]));
    }
}
