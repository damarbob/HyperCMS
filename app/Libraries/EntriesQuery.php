<?php

namespace App\Libraries;

use App\Data\Entries\FieldRules;
use Closure;

/**
 * Answers a data query on `table: entries` in PHP.
 *
 * The current fields of an entry are no longer in a SQL column, so the query cannot run in the database as it
 * once did. This class answers the part of it that can be answered without that column, and refuses the rest
 * with an UnsupportedDataQuery instead of returning a list that is wrong.
 *
 * What is answered:
 * - `select` of `id`, `model_id`, `model_name`, `fields` and `{{field:x}}`, each with an optional `as alias`.
 * - `where` on `model_id` and `model_name`, with the operators `=`, `!=`, `<>`, `LIKE`, `IN` and `NOT IN`.
 * - `like` on `fields`, which matches the text of the fields anywhere, ignoring case and accents.
 * - `orderby` on those columns, a placeholder or a select alias, then `limit`, `offset` and `count`.
 *
 * Everything else (`joins`, `groupby`, `having`, other columns, `{{model_field:x}}`) is refused. For example
 * `select: "id as value, fields as label"` returns `[{"value":"5","label":"{\"title\":\"Hello\"}"}, ...]`,
 * and `select: "created_by"` is refused as `select created_by`.
 */
final class EntriesQuery
{
    private const COLUMNS   = ['id', 'model_id', 'model_name', 'fields'];
    private const KEYS      = ['table', 'select', 'where', 'like', 'orderby', 'limit', 'offset', 'count'];
    private const OPERATORS = ['=', '!=', '<>', 'LIKE', 'IN', 'NOT IN'];

    /**
     * @param Closure(): list<array{id: int|string, model_id: int|string, model_name: ?string, sd_entry_id: int|string|null, payload_bytes: int|string}> $flat
     *        Every live entry without its fields.
     * @param Closure(list<array>): iterable<array<int, array>> $fieldsOf
     *        Reads the fields of the given rows a chunk at a time, yielding the fields by entry id for each chunk.
     */
    public function __construct(private Closure $flat, private Closure $fieldsOf)
    {
    }

    /**
     * Answer a query.
     *
     * @return list<array<string, ?string>>|int The selected rows, or their number when the query asks for a count.
     *
     * @throws UnsupportedDataQuery
     */
    public function run(array $params): array|int
    {
        $plan = $this->parse($params);

        $rows = array_values(array_filter(($this->flat)(), fn (array $row): bool => $this->passes($row, $plan['where'])));

        if ($plan['needsFields']) {
            $rows = $this->withFields($rows, $plan);
        }

        if ($plan['order'] !== []) {
            usort($rows, fn (array $a, array $b): int => $this->compareRows($a, $b, $plan['order']));
        }

        if ($plan['count']) {
            return count($rows);
        }

        if ($plan['limit'] > 0) {
            $rows = array_slice($rows, $plan['offset'], $plan['limit']);
        }

        $result = [];
        foreach ($rows as $row) {
            $out = [];
            foreach ($plan['select'] as $alias => [$kind, $name]) {
                $out[$alias] = $this->value($row, $kind, $name);
            }
            $result[] = $out;
        }

        return $result;
    }

    /**
     * Check the query and turn it into a plan.
     *
     * @return array{select: array<string, array{0: string, 1: string}>, where: list<array{0: string, 1: string, 2: mixed}>, like: ?string, order: list<array{0: string, 1: string, 2: bool}>, limit: int, offset: int, count: bool, needsFields: bool, names: list<string>}
     *
     * @throws UnsupportedDataQuery
     */
    public function parse(array $params): array
    {
        foreach (['joins', 'groupby', 'having'] as $key) {
            if (! empty($params[$key])) {
                throw new UnsupportedDataQuery($key);
            }
        }
        foreach (array_keys($params) as $key) {
            if (! in_array($key, self::KEYS, true) && ! in_array($key, ['joins', 'groupby', 'having'], true)) {
                throw new UnsupportedDataQuery('key ' . $key);
            }
        }

        $select = $this->parseSelect($params['select'] ?? '');
        $order  = $this->parseOrder($params['orderby'] ?? '', $select);

        $like = null;
        if (! empty($params['like'])) {
            if (! is_array($params['like'])) {
                throw new UnsupportedDataQuery('like');
            }
            foreach ($params['like'] as $column => $term) {
                if ($column !== 'fields') {
                    throw new UnsupportedDataQuery('like on ' . $column);
                }
                if (! is_scalar($term)) {
                    throw new UnsupportedDataQuery('like on fields with a list');
                }
                $like = (string) $term;
            }
        }

        // The fields are read only when something needs them.
        $names = [];
        $needs = $like !== null;
        foreach ([$select, ...array_map(static fn (array $item): array => [$item], $order)] as $list) {
            foreach ($list as [$kind, $name]) {
                if ($kind === 'field') {
                    $names[$name] = true;
                    $needs        = true;
                } elseif ($name === 'fields') {
                    $needs = true;
                }
            }
        }

        return [
            'select' => $select,
            'where'  => $this->parseWhere($params['where'] ?? []),
            'like'   => $like,
            'order'  => $order,
            'limit'  => max(0, (int) ($params['limit'] ?? 0)),
            'offset' => max(0, (int) ($params['offset'] ?? 0)),
            'count'  => ($params['count'] ?? false) === true,
            'needsFields' => $needs,
            'names'  => array_keys($names),
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}> The alias of each column, with its kind (`column` or `field`) and name.
     */
    private function parseSelect(mixed $select): array
    {
        if (! is_string($select) || trim($select) === '') {
            throw new UnsupportedDataQuery('select without columns');
        }

        $items = [];
        foreach (self::split($select) as $item) {
            if (! preg_match('/^(.+?)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?$/is', $item, $m)) {
                throw new UnsupportedDataQuery('select ' . $item);
            }
            [$kind, $name] = $this->expression(trim($m[1]), 'select');
            $items[$m[2] ?? $name] = [$kind, $name];
        }

        return $items;
    }

    /**
     * @param array<string, array{0: string, 1: string}> $select
     *
     * @return list<array{0: string, 1: string, 2: bool}> The kind, the name and whether the order is descending.
     */
    private function parseOrder(mixed $orderby, array $select): array
    {
        if ($orderby === '' || $orderby === null || $orderby === []) {
            return [];
        }
        if (! is_string($orderby)) {
            throw new UnsupportedDataQuery('orderby');
        }

        $order = [];
        foreach (self::split($orderby) as $item) {
            if (! preg_match('/^(.+?)(?:\s+(asc|desc))?$/is', $item, $m)) {
                throw new UnsupportedDataQuery('orderby ' . $item);
            }
            $target = trim($m[1]);
            [$kind, $name] = $select[$target] ?? $this->expression($target, 'orderby');
            $order[]       = [$kind, $name, strtolower($m[2] ?? 'asc') === 'desc'];
        }

        return $order;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function expression(string $text, string $where): array
    {
        if (preg_match('/^\{\{field:([^}]+)\}\}$/', $text, $m)) {
            return ['field', trim($m[1])];
        }
        if (in_array($text, self::COLUMNS, true)) {
            return ['column', $text];
        }

        throw new UnsupportedDataQuery($where . ' ' . $text);
    }

    /**
     * @return list<array{0: string, 1: string, 2: mixed}> The column, the operator and the value of each condition.
     */
    private function parseWhere(mixed $where): array
    {
        if ($where === [] || $where === '' || $where === null) {
            return [];
        }

        $conditions = [];
        foreach (is_array($where) && array_is_list($where) ? $where : [$where] as $condition) {
            if (is_string($condition)) {
                $conditions[] = $this->parseWhereText($condition);
            } elseif (is_array($condition) && isset($condition['column'], $condition['operator'], $condition['value'])) {
                $conditions[] = $this->condition((string) $condition['column'], (string) $condition['operator'], $condition['value']);
            } elseif (is_array($condition)) {
                foreach ($condition as $key => $value) {
                    if (! preg_match('/^\s*(\w+)\s*(=|!=|<>)?\s*$/', (string) $key, $m)) {
                        throw new UnsupportedDataQuery('where ' . $key);
                    }
                    $conditions[] = $this->condition($m[1], $m[2] ?? '=', $value);
                }
            } else {
                throw new UnsupportedDataQuery('where');
            }
        }

        return $conditions;
    }

    /**
     * Read a plain condition such as `model_id = 3` or `model_name = 'Article'`.
     */
    private function parseWhereText(string $text): array
    {
        $pattern = '/^\s*(\w+)\s*(=|!=|<>)\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"|(-?\d+))\s*$/';
        if (! preg_match($pattern, $text, $m)) {
            throw new UnsupportedDataQuery('where ' . $text);
        }

        if (isset($m[5]) && $m[5] !== '') {
            $value = $m[5];
        } elseif (isset($m[4]) && $m[4] !== '') {
            $value = stripslashes($m[4]);
        } else {
            $value = stripslashes($m[3] ?? '');
        }

        return $this->condition($m[1], $m[2], $value);
    }

    /**
     * @return array{0: string, 1: string, 2: mixed}
     */
    private function condition(string $column, string $operator, mixed $value): array
    {
        if (! in_array($column, ['model_id', 'model_name'], true)) {
            throw new UnsupportedDataQuery('where on column ' . $column);
        }

        $operator = strtoupper(trim($operator));
        if (! in_array($operator, self::OPERATORS, true)) {
            throw new UnsupportedDataQuery('operator ' . $operator);
        }

        if (in_array($operator, ['IN', 'NOT IN'], true)) {
            $value = is_array($value) ? array_values($value) : [$value];
        }
        foreach ((array) $value as $item) {
            if (! is_scalar($item)) {
                throw new UnsupportedDataQuery('where on column ' . $column . ' with a nested value');
            }
        }

        return [$column, $operator, $value];
    }

    /**
     * Whether an entry satisfies every condition on its model.
     *
     * @param list<array{0: string, 1: string, 2: mixed}> $where
     */
    private function passes(array $row, array $where): bool
    {
        foreach ($where as [$column, $operator, $value]) {
            $actual = (string) ($row[$column] ?? '');
            $same   = $column === 'model_id'
                ? static fn (mixed $other): bool => trim((string) $other) === $actual
                : static fn (mixed $other): bool => FieldRules::sortFold((string) $other) === FieldRules::sortFold($actual);
            $any    = static fn (array $values): bool => array_reduce($values, static fn (bool $found, mixed $item): bool => $found || $same($item), false);

            $hit = match ($operator) {
                '='        => $same($value),
                '!=', '<>' => ! $same($value),
                'IN'       => $any($value),
                'NOT IN'   => ! $any($value),
                'LIKE'     => self::like($actual, (string) $value),
                default    => false,
            };

            if (! $hit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read the fields of the rows, keep the values the query needs, and drop the rows the `like` does not match.
     *
     * @param list<array> $rows
     *
     * @return list<array>
     */
    private function withFields(array $rows, array $plan): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $textNeeded = $plan['like'] !== null || in_array('fields', array_column($plan['select'], 1), true)
            || in_array('fields', array_column($plan['order'], 1), true);

        $kept = [];
        foreach (($this->fieldsOf)($rows) as $chunk) {
            foreach ($chunk as $id => $fields) {
                if (! isset($byId[$id])) {
                    continue;
                }

                $row = $byId[$id];
                $text = $textNeeded ? FieldRules::fieldsText($fields) : '';
                if ($plan['like'] !== null && ! str_contains(FieldRules::fold($text), FieldRules::fold($plan['like']))) {
                    continue;
                }

                $row['__fields'] = $text;
                foreach ($plan['names'] as $name) {
                    $row['__f'][$name] = array_key_exists($name, $fields) ? FieldRules::jsonText($fields[$name]) : null;
                }
                $kept[$id] = $row;
            }
        }

        // Keep the order the rows had, whatever order the chunks answered in.
        return array_values(array_filter(array_map(static fn (array $row): ?array => $kept[(int) $row['id']] ?? null, $rows)));
    }

    private function value(array $row, string $kind, string $name): ?string
    {
        if ($kind === 'field') {
            return $row['__f'][$name] ?? null;
        }

        return match ($name) {
            'fields' => $row['__fields'] ?? null,
            default  => isset($row[$name]) ? (string) $row[$name] : null,
        };
    }

    /**
     * Order two rows by each key in turn, and by id when they tie. A number sorts as a number, text by the same
     * rule the sort of a list uses, and a missing value before everything else.
     *
     * @param list<array{0: string, 1: string, 2: bool}> $order
     */
    private function compareRows(array $a, array $b, array $order): int
    {
        foreach ($order as [$kind, $name, $descending]) {
            $x = $this->value($a, $kind, $name);
            $y = $this->value($b, $kind, $name);

            if ($x === null || $y === null) {
                $result = ($x === null ? 0 : 1) <=> ($y === null ? 0 : 1);
            } elseif (is_numeric($x) && is_numeric($y)) {
                $result = (float) $x <=> (float) $y;
            } else {
                $result = strcmp(FieldRules::sortFold($x), FieldRules::sortFold($y));
            }

            if ($result !== 0) {
                return $descending ? -$result : $result;
            }
        }

        return (int) $a['id'] <=> (int) $b['id'];
    }

    /**
     * Split a list on its commas, leaving the commas inside `{{ }}` alone.
     *
     * @return list<string>
     */
    private static function split(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/,(?![^{]*\}\})/', $text) ?: []), static fn (string $item): bool => $item !== ''));
    }

    /**
     * `LIKE` on the whole text, folded as the search folds: `%` is any run of characters and `_` any one.
     */
    private static function like(string $text, string $pattern): bool
    {
        $regex = '';
        foreach (preg_split('//u', FieldRules::fold($pattern), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $regex .= match ($char) {
                '%'     => '.*',
                '_'     => '.',
                default => preg_quote($char, '/'),
            };
        }

        return preg_match('/^' . $regex . '$/su', FieldRules::fold($text)) === 1;
    }
}
