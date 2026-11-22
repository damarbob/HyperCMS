<?php

namespace App\Libraries\Upgrade\Convert;

use App\Fields\FieldTypes;

/**
 * Turns the field definition of one model from the old format into version 2. It reads arrays and returns arrays, and
 * touches no database, so it can be tested on plain data.
 *
 * A field that cannot be converted becomes a text field and is listed in `problems`, so the upgrade can report it.
 * A field without an id is dropped and listed. A field id that repeats counts once, by its first entry, as it does
 * when the model is registered.
 */
final class ModelFields
{
    public function __construct(private readonly FieldTypes $types)
    {
    }

    /**
     * @param list<mixed>          $old      The decoded old definition. An entry that is not an object is ignored.
     * @param array<string, mixed> $modelIds The id of each model by name, for a data query that names its model.
     *
     * @return array{
     *     fields: list<array<string, mixed>>,
     *     problems: list<array{field: string, problem: string}>,
     *     labels: list<array{model: int, field: string, from: string}>
     * } `labels` holds, for each relation, the field of the target model the old query showed as its label.
     */
    public function convert(array $old, array $modelIds = []): array
    {
        $fields   = [];
        $problems = [];
        $labels   = [];
        $seen     = [];

        foreach ($old as $position => $field) {
            if (! is_array($field)) {
                continue;
            }

            $result = $this->types->fromLegacy($field, ['modelIds' => $modelIds]);
            $id     = is_scalar($field['id'] ?? null) ? (string) $field['id'] : '';

            if ($result['problem'] !== null) {
                $problems[] = ['field' => $id !== '' ? $id : '#' . $position, 'problem' => $result['problem']];
            }
            if ($result['field'] === null || isset($seen[$result['field']['id']])) {
                continue;
            }

            $seen[$result['field']['id']] = true;
            $fields[]                     = $result['field'];

            if ($result['labelField'] !== null && ($result['field']['type'] ?? null) === 'relation') {
                $labels[] = ['model' => (int) $result['field']['model'], 'field' => $result['labelField'], 'from' => $result['field']['id']];
            }
        }

        return ['fields' => $fields, 'problems' => $problems, 'labels' => $labels];
    }
}
