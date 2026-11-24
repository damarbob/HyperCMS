<?php

namespace App\Libraries;

/**
 * What one tick did, as lines to show, and what went wrong.
 */
final class TickOutcome
{
    /** @var list<string> */
    public array $lines = [];

    /** @var list<string> */
    public array $failures = [];

    public function failed(): bool
    {
        return $this->failures !== [];
    }
}
