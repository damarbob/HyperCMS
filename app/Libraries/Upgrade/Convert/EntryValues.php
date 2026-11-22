<?php

namespace App\Libraries\Upgrade\Convert;

use App\Fields\ConversionProblem;
use App\Fields\FieldTypes;

/**
 * Turns the field values of one entry, or of one entry version, from the old shape into the version 2 shape. It reads
 * arrays and returns arrays, and touches no database.
 *
 * Only keys that exist are converted, and a key is never added. A value that fits no rule of its type stays as it
 * was and is listed in `problems`. A key the definition does not name is left alone.
 */
final class EntryValues
{
    public function __construct(private readonly FieldTypes $types)
    {
    }

    /**
     * @param array<string, mixed>       $fields      The values of the entry by field id.
     * @param list<array<string, mixed>> $definitions The version 2 definition of its model.
     *
     * @return array{fields: array<string, mixed>, problems: list<array{field: string, problem: string}>}
     */
    public function convert(array $fields, array $definitions): array
    {
        $byId = [];
        foreach ($definitions as $definition) {
            if (is_string($definition['id'] ?? null) && ! isset($byId[$definition['id']])) {
                $byId[$definition['id']] = $definition;
            }
        }

        $problems = [];
        foreach ($fields as $id => $value) {
            $definition = $byId[(string) $id] ?? null;
            $type       = $definition === null ? null : $this->types->get((string) ($definition['type'] ?? ''));
            if ($type === null) {
                continue;
            }

            try {
                $fields[$id] = $type->valueFromLegacy($value, $definition);
            } catch (ConversionProblem $problem) {
                $problems[] = ['field' => (string) $id, 'problem' => $problem->getMessage()];
            }
        }

        return ['fields' => $fields, 'problems' => $problems];
    }
}
