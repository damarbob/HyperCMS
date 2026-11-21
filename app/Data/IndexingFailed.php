<?php

namespace App\Data;

use RuntimeException;

/**
 * Thrown when a model was saved but StarDust could not start indexing one of its filterable fields, for example
 * because the field is being renamed. The message is meant for the admin, who can save the model again to retry.
 */
class IndexingFailed extends RuntimeException
{
    /**
     * @param array<string, string> $failures The reason StarDust gave, by field id.
     */
    public function __construct(public readonly array $failures)
    {
        parent::__construct(lang('Filterable.indexingFailed', [
            'fields' => implode(', ', array_keys($failures)),
            'reason' => implode(' ', array_unique($failures)),
        ]));
    }
}
