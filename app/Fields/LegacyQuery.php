<?php

namespace App\Fields;

/**
 * Reads the options of an old field that came from a data query, such as
 * `{"type":"data","content":{"table":"models","select":"id as value, name as label"}}`, and says which version 2 field
 * replaces it.
 *
 * Only three shapes convert. Every other query is refused with a reason, and the upgrade turns that field into a text
 * field and lists it.
 *
 * | Query                                                                  | Becomes    |
 * | :--                                                                    | :--        |
 * | `table: models`, `select: id as value, name as label`                 | `model`    |
 * | `table: entries`, `select: id as value, {{field:x}} as label`, `where` one model | `relation` |
 * | `content: hooks`, with an optional `group`                             | `hook`     |
 */
final class LegacyQuery
{
    /**
     * Whether the options are a data query at all, convertible or not.
     */
    public static function isQuery(mixed $options): bool
    {
        return is_array($options) && ($options['type'] ?? null) === 'data';
    }

    /**
     * @param mixed                $options  The `options` of the old field.
     * @param array<string, mixed> $modelIds The id of each model by name, for a query that names its model.
     *
     * @return array{type: 'model'}|array{type: 'relation', model: int, label: string}|array{type: 'hook', group: ?string}
     *
     * @throws ConversionProblem With the reason the query cannot be converted.
     */
    public static function classify(mixed $options, array $modelIds = []): array
    {
        if (! self::isQuery($options)) {
            throw new ConversionProblem('the options are not a data query');
        }

        $content = $options['content'] ?? null;
        if ($content === 'hooks') {
            $group = $options['group'] ?? null;

            return ['type' => 'hook', 'group' => is_string($group) && $group !== '' ? $group : null];
        }
        if (! is_array($content)) {
            throw new ConversionProblem('the data query has no table');
        }

        $table = is_string($content['table'] ?? null) ? strtolower($content['table']) : '';

        return match ($table) {
            'models'  => self::models($content),
            'entries' => self::entries($content, $modelIds),
            default   => throw new ConversionProblem('the data query reads the table ' . ($table === '' ? '(none)' : $table)),
        };
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{type: 'model'}
     */
    private static function models(array $query): array
    {
        self::onlyKeys($query, ['table', 'select']);
        $select = self::select($query['select'] ?? null);

        if (($select['value'] ?? null) !== 'id' || ($select['label'] ?? null) !== 'name' || count($select) !== 2) {
            throw new ConversionProblem('the data query on models selects something other than id as value and name as label');
        }

        return ['type' => 'model'];
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $modelIds
     *
     * @return array{type: 'relation', model: int, label: string}
     */
    private static function entries(array $query, array $modelIds): array
    {
        self::onlyKeys($query, ['table', 'select', 'where', 'orderby']);
        $select = self::select($query['select'] ?? null);

        if (count($select) !== 2 || ($select['value'] ?? null) !== 'id'
            || preg_match('/^\{\{field:([^}]+)\}\}$/', (string) ($select['label'] ?? ''), $m) !== 1) {
            throw new ConversionProblem('the data query on entries selects something other than id as value and one field as label');
        }

        $conditions = self::conditions($query['where'] ?? null);
        if (count($conditions) !== 1) {
            throw new ConversionProblem('the data query on entries must name exactly one model');
        }
        [$column, $value] = $conditions[0];

        if ($column === 'model_id') {
            if (preg_match('/^[0-9]+$/', (string) $value) !== 1 || (int) $value < 1) {
                throw new ConversionProblem('the model id "' . $value . '" is not a number');
            }
            $model = (int) $value;
        } else {
            $found = null;
            foreach ($modelIds as $name => $id) {
                if (mb_strtolower((string) $name) === mb_strtolower((string) $value)) {
                    $found = (int) $id;

                    break;
                }
            }
            if ($found === null) {
                throw new ConversionProblem('there is no model named "' . $value . '"');
            }
            $model = $found;
        }

        return ['type' => 'relation', 'model' => $model, 'label' => trim($m[1])];
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string>         $allowed
     */
    private static function onlyKeys(array $query, array $allowed): void
    {
        foreach (array_keys($query) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw new ConversionProblem('the data query uses ' . $key);
            }
        }
    }

    /**
     * Read `id as value, name as label` into the alias and column of each item.
     *
     * @return array<string, string> The column by alias.
     */
    private static function select(mixed $select): array
    {
        if (! is_string($select) || trim($select) === '') {
            throw new ConversionProblem('the data query has no select');
        }

        $items = [];
        foreach (explode(',', $select) as $item) {
            if (preg_match('/^\s*(.+?)\s+as\s+(\w+)\s*$/is', $item, $m) !== 1) {
                throw new ConversionProblem('the select "' . trim($item) . '" has no alias');
            }
            $column                   = trim($m[1]);
            $items[strtolower($m[2])] = str_starts_with($column, '{{') ? $column : strtolower($column);
        }

        return $items;
    }

    /**
     * Read the `where` of a query into a list of column and value, accepting only the `=` operator.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function conditions(mixed $where): array
    {
        if ($where === null || $where === '' || $where === []) {
            return [];
        }

        $found = [];
        foreach (is_array($where) && array_is_list($where) ? $where : [$where] as $condition) {
            if (is_string($condition)) {
                $found[] = self::textCondition($condition);
            } elseif (is_array($condition) && isset($condition['column'], $condition['operator'], $condition['value'])) {
                $found[] = self::checked((string) $condition['column'], (string) $condition['operator'], $condition['value']);
            } elseif (is_array($condition)) {
                foreach ($condition as $key => $value) {
                    if (preg_match('/^\s*(\w+)\s*(=)?\s*$/', (string) $key, $m) !== 1) {
                        throw new ConversionProblem('the where "' . $key . '" is not a plain condition');
                    }
                    $found[] = self::checked($m[1], '=', $value);
                }
            } else {
                throw new ConversionProblem('the where is not a plain condition');
            }
        }

        return $found;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function textCondition(string $text): array
    {
        $pattern = '/^\s*(\w+)\s*(=|!=|<>)\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"|(-?\d+))\s*$/';
        if (preg_match($pattern, $text, $m) !== 1) {
            throw new ConversionProblem('the where "' . trim($text) . '" is not a plain condition');
        }

        if (isset($m[5]) && $m[5] !== '') {
            $value = $m[5];
        } elseif (isset($m[4]) && $m[4] !== '') {
            $value = stripslashes($m[4]);
        } else {
            $value = stripslashes($m[3] ?? '');
        }

        return self::checked($m[1], $m[2], $value);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function checked(string $column, string $operator, mixed $value): array
    {
        $column = strtolower($column);
        if (! in_array($column, ['model_id', 'model_name'], true)) {
            throw new ConversionProblem('the where is on the column ' . $column);
        }
        if (trim($operator) !== '=') {
            throw new ConversionProblem('the where uses the operator ' . trim($operator));
        }
        if (! is_scalar($value)) {
            throw new ConversionProblem('the where has a value that is not one text or number');
        }

        return [$column, (string) $value];
    }
}
