<?php

namespace App\Data\Entries;

use InvalidArgumentException;
use StarDust\Filter\Ast\AndNode;
use StarDust\Filter\Ast\FilterNode;
use StarDust\Filter\Ast\LeafNode;

/**
 * Turns the `filter` query parameter of the token API into a StarDust filter on indexed fields.
 *
 * `filter[views]=12` means equal, and `filter[views][gte]=10&filter[views][lt]=20` gives several conditions on one
 * field. Every condition of the request must hold. A value is read as the type its field is indexed as, so a number
 * field takes numbers and a date field takes a date and time. A date without an offset is read as UTC, because
 * StarDust stores dates in UTC and the admin form saves them without one.
 */
final class FieldFilters
{
    /**
     * The operators a request may use, with what each one takes.
     *
     * @var array<string, string> Operator to `one`, `list` or `none`.
     */
    public const OPERATORS = [
        'eq'          => 'one',
        'neq'         => 'one',
        'lt'          => 'one',
        'lte'         => 'one',
        'gt'          => 'one',
        'gte'         => 'one',
        'prefix'      => 'one',
        'in'          => 'list',
        'is_null'     => 'none',
        'is_not_null' => 'none',
    ];

    /**
     * The most values an `in` list may hold.
     */
    private const MAX_LIST = 100;

    /**
     * Read the filter of a request.
     *
     * @param array<mixed>          $filter  The decoded `filter` parameter, by field id.
     * @param array<string, string> $indexed The fields that can be filtered on, with their StarDust type.
     *
     * @return FilterNode|null Null when the request has no condition.
     *
     * @throws InvalidArgumentException With a message for the caller when a field, an operator or a value cannot be used.
     */
    public static function parse(array $filter, array $indexed): ?FilterNode
    {
        $leaves = [];

        foreach ($filter as $field => $conditions) {
            $field = (string) $field;
            if (! isset($indexed[$field])) {
                throw new InvalidArgumentException(lang('Filterable.notIndexed', [
                    'field'   => $field,
                    'indexed' => $indexed === [] ? lang('Filterable.noIndexedFields') : implode(', ', array_keys($indexed)),
                ]));
            }

            // A plain value is an equality.
            if (! is_array($conditions)) {
                $conditions = ['eq' => $conditions];
            }
            foreach ($conditions as $operator => $value) {
                $operator = (string) $operator;
                if (! isset(self::OPERATORS[$operator])) {
                    throw new InvalidArgumentException(lang('Filterable.badOperator', ['operator' => $operator, 'operators' => implode(', ', array_keys(self::OPERATORS))]));
                }
                $leaves[] = self::leaf($field, $indexed[$field], $operator, $value);
            }
        }

        return match (count($leaves)) {
            0       => null,
            1       => $leaves[0],
            default => new AndNode($leaves),
        };
    }

    private static function leaf(string $field, string $type, string $operator, mixed $value): LeafNode
    {
        $takes = self::OPERATORS[$operator];

        if ($takes === 'none') {
            return LeafNode::local($field, $operator, null);
        }
        if ($takes === 'list') {
            if (is_array($value)) {
                $parts = array_values($value);
            } else {
                $parts = is_string($value) && $value !== '' ? explode(',', $value) : [];
            }
            if ($parts === [] || count($parts) > self::MAX_LIST) {
                throw new InvalidArgumentException(lang('Filterable.badFilter', ['field' => $field, 'reason' => lang('Filterable.badList', ['max' => self::MAX_LIST])]));
            }

            return LeafNode::local($field, $operator, array_map(static fn (mixed $part): mixed => self::coerce($field, $type, $operator, $part), $parts));
        }

        return LeafNode::local($field, $operator, self::coerce($field, $type, $operator, $value));
    }

    /**
     * Read a value of the request as the type its field is indexed as.
     */
    private static function coerce(string $field, string $type, string $operator, mixed $value): mixed
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException(lang('Filterable.badValue', ['field' => $field, 'value' => get_debug_type($value)]));
        }
        $text = (string) $value;
        $bad  = new InvalidArgumentException(lang('Filterable.badValue', ['field' => $field, 'value' => mb_substr($text, 0, 40)]));

        if ($operator === 'prefix' && $type !== 'string') {
            throw new InvalidArgumentException(lang('Filterable.badFilter', ['field' => $field, 'reason' => lang('Filterable.prefixText')]));
        }

        switch ($type) {
            case 'int':
            case 'numeric':
                try {
                    $number = FilterableFields::value($field, $type, $text);
                } catch (InvalidArgumentException) {
                    throw $bad;
                }
                if ($number === null) {
                    throw $bad;
                }
                if (is_string($number)) {
                    $number = preg_match('/^-?[0-9]+$/', $number) === 1 && (string) (int) $number === $number ? (int) $number : (float) $number;
                }

                return $number;

            case 'datetime':
                // An offset is kept, and a date without one is UTC. The form's own `T` and missing seconds are accepted.
                if (preg_match('/(Z|[+-]\d{2}:\d{2})$/', $text) === 1) {
                    return $text;
                }

                try {
                    $clean = FilterableFields::value($field, 'datetime', $text);
                } catch (InvalidArgumentException) {
                    throw $bad;
                }
                if ($clean === null) {
                    throw $bad;
                }

                return str_replace(' ', 'T', $clean) . 'Z';

            default:
                if (mb_strlen($text) > FilterableFields::MAX_STRING) {
                    throw $bad;
                }

                return $text;
        }
    }
}
