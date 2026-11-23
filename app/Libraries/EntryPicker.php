<?php

namespace App\Libraries;

use App\Fields\FieldTypes;

/**
 * Finds the entries of one model for a picker: the entries whose label holds what the person typed, or the entry with
 * that number. With nothing typed it offers the entries edited last.
 *
 * The search reads the model once and keeps the ordered ids in the cache, as a search in a list does, so a second
 * letter typed does not read it again.
 */
final class EntryPicker
{
    /**
     * The entries a search looks through at most. A person who needs more types more.
     */
    private const READ = 100;

    public function __construct(private readonly FieldTypes $types)
    {
    }

    /**
     * @param array<string, mixed> $model The model as `ModelStore::findArray()` returns it.
     *
     * @return array{items: list<array{id: int, label: string}>, more: bool, error: string}
     */
    public function search(array $model, string $q, int $limit = 20): array
    {
        $fields = json_decode((string) ($model['fields'] ?? '[]'), true);
        $fields = is_array($fields) ? array_values(array_filter($fields, static fn ($f): bool => is_array($f) && is_string($f['id'] ?? null))) : [];
        $label  = is_string($model['label_field'] ?? null) && $model['label_field'] !== '' ? $model['label_field'] : null;

        $query   = new EntryListQuery(service('entryStore'), $this->types);
        $request = $query->parse(['q' => $q, 'per' => self::READ], $fields, self::READ);
        $result  = $query->run((int) $model['id'], $request);

        $needle = mb_strtolower(trim($q));
        $items  = [];
        $more   = false;
        foreach ($result['rows'] as $row) {
            $name = $label !== null && is_scalar($row['fields'][$label] ?? null) && (string) $row['fields'][$label] !== '' ? (string) $row['fields'][$label] : '#' . $row['id'];

            // The list search looks in every field, and a picker answers for the name and the number only.
            if ($needle !== '' && ! str_contains(mb_strtolower($name), $needle) && (string) $row['id'] !== ltrim($needle, '#')) {
                continue;
            }
            if (count($items) >= $limit) {
                $more = true;
                break;
            }
            $items[] = ['id' => (int) $row['id'], 'label' => $name];
        }

        return ['items' => $items, 'more' => $more, 'error' => (string) $result['error']];
    }
}
