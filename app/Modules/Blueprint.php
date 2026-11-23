<?php

namespace App\Modules;

/**
 * A model a module needs. The module states the fields it expects, in version 2, and the admin offers to make a model
 * from them or to pick an existing model that has them. The choice is kept in the settings under `Modules.<module>.<key>`,
 * and the module reads the model id from `service('modules')->modelOf($module, $key)`, so no module scans the models.
 */
final class Blueprint
{
    /**
     * @param string                       $key         A name for the model inside the module, such as `page`.
     * @param list<array<string, mixed>>   $fields      The fields the model must have, as version 2 definitions.
     * @param string|null                  $labelField  The id of the field that names the entries.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly array $fields,
        public readonly ?string $labelField = null,
    ) {
    }

    /**
     * Whether a model's fields include every field the blueprint asks for, with the same type.
     *
     * @param list<array<string, mixed>> $modelFields
     */
    public function fits(array $modelFields): bool
    {
        $byId = [];
        foreach ($modelFields as $field) {
            if (is_array($field) && is_string($field['id'] ?? null)) {
                $byId[$field['id']] = $field['type'] ?? null;
            }
        }

        foreach ($this->fields as $wanted) {
            if (($byId[$wanted['id'] ?? ''] ?? null) !== ($wanted['type'] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
