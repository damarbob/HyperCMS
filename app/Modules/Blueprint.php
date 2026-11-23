<?php

namespace App\Modules;

/**
 * A model a module needs. The module states the fields it expects, in version 2, and the admin offers to make a model
 * from them or to pick an existing model that has them. The choice is kept in the settings under the name that `Modules::settingKey()` gives,
 * and the module reads the model id from `service('modules')->modelOf($module, $key)`, so no module scans the models.
 */
final class Blueprint
{
    /**
     * @param string                       $key         A name for the model inside the module, such as `page`.
     * @param list<array<string, mixed>>   $fields      The fields the model must have, as version 2 definitions.
     * @param string|null                  $labelField  The id of the field that names the entries.
     * @param string                       $icon        The icon of a model made from the blueprint.
     * @param array<string, list<string>>  $accepts     Other types a field may have and still fit, by field id. A model
     *                                                  that holds a link as text fits a blueprint that asks for a file.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly array $fields,
        public readonly ?string $labelField = null,
        public readonly string $icon = 'doc',
        public readonly array $accepts = [],
    ) {
    }

    /**
     * Whether a model's fields include every field the blueprint asks for, with the same type or one it accepts.
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
            $id   = (string) ($wanted['id'] ?? '');
            $have = $byId[$id] ?? null;
            if ($have !== ($wanted['type'] ?? null) && ! in_array($have, $this->accepts[$id] ?? [], true)) {
                return false;
            }
        }

        return true;
    }
}
