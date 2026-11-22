<?php

namespace App\Libraries\Upgrade\Convert;

/**
 * Picks the label field of a model, the field that names an entry in lists and pickers.
 *
 * The first hint that names a field of the model wins. A hint comes from an old data query that showed that field of
 * this model as the label of a select. Without one, the pick is the first `text` field whose id is `title` or `name`,
 * else the first `text` field, else none.
 */
final class LabelField
{
    /**
     * @param list<array<string, mixed>> $fields The version 2 definition of the model.
     * @param list<string>               $hints  Field ids, in the order they were found.
     *
     * @return string|null The id of the field, or null when the model has no field to name an entry by.
     */
    public static function pick(array $fields, array $hints = []): ?string
    {
        $byId = [];
        foreach ($fields as $field) {
            if (is_string($field['id'] ?? null) && ! isset($byId[$field['id']])) {
                $byId[$field['id']] = $field;
            }
        }

        foreach ($hints as $hint) {
            if (isset($byId[$hint]) && in_array($byId[$hint]['type'] ?? null, ['text', 'choice', 'number', 'datetime', 'relation', 'model'], true)) {
                return $hint;
            }
        }

        $texts = array_values(array_filter($byId, static fn (array $field): bool => ($field['type'] ?? null) === 'text'));
        foreach ($texts as $field) {
            if (in_array(strtolower($field['id']), ['title', 'name'], true)) {
                return $field['id'];
            }
        }

        return $texts[0]['id'] ?? null;
    }
}
