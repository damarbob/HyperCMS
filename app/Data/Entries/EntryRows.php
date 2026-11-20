<?php

namespace App\Data\Entries;

use LogicException;

/**
 * Builds the legacy row an entry has always been returned as, from a hyper_entries row, its model,
 * the usernames and the entry's fields in StarDust 0.3.
 *
 * This is a skeleton. Phase A6 fills it in and owns this file.
 */
class EntryRows
{
    /**
     * Assemble one legacy row.
     *
     * @param array $entry A hyper_entries row joined to its model name and the usernames.
     * @param array|null $fields The entry's fields as StarDust returned them, or null when it has none.
     */
    public function assemble(array $entry, ?array $fields): array
    {
        throw new LogicException(static::class . '::' . __FUNCTION__ . '() is not ported to StarDust 0.3 yet.');
    }
}
