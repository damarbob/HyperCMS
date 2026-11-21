<?php

namespace App\Libraries;

use InvalidArgumentException;

/**
 * A data query on `table: entries` that the PHP subset in EntriesQuery does not answer.
 * `part` names what was refused, for example `joins` or `where on column created_by`.
 */
class UnsupportedDataQuery extends InvalidArgumentException
{
    public function __construct(public readonly string $part)
    {
        parent::__construct('Unsupported data query part: ' . $part);
    }
}
