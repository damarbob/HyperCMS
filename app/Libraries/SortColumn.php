<?php

namespace App\Libraries;

/**
 * Checks a sort column that came from a request before it reaches a query.
 *
 * CodeIgniter leaves a sort value unescaped when it contains a parenthesis, so a
 * visitor could send a subquery as the sort expression. Only a plain column name
 * passes this check, with an optional table name in front of it.
 */
class SortColumn
{
    public static function isSafe(mixed $column): bool
    {
        return is_string($column)
            && preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column) === 1;
    }
}
