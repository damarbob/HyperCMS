<?php

namespace App\Fields;

/**
 * Checks the definition of a model against version 2 before it is saved, whether it came from the visual builder or from
 * the JSON editor, and returns it in the shape it is stored in.
 *
 * Every problem is reported with the id of the field it belongs to, so the builder can show it on that field. A key the
 * check does not know is kept as it is, because a module may add keys of its own.
 */
final class DefinitionCheck
{
    public const MAX_FIELDS = 200;

    private const ID = '/^[A-Za-z][A-Za-z0-9_]{0,63}$/';

    /**
     * @param array<int, string> $modelIds The models a relation can point at, as id to name.
     */
    public function __construct(private readonly FieldTypes $types, private readonly array $modelIds = [])
    {
    }

    /**
     * @return array{fields: list<array<string, mixed>>, errors: list<array{field: string|null, message: string}>}
     */
    public function check(mixed $decoded): array
    {
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return ['fields' => [], 'errors' => [['field' => null, 'message' => lang('Models.checkNotList')]]];
        }
        if (count($decoded) > self::MAX_FIELDS) {
            return ['fields' => [], 'errors' => [['field' => null, 'message' => lang('Models.checkTooMany', ['max' => self::MAX_FIELDS])]]];
        }

        $fields = [];
        $errors = [];
        $seen   = [];

        foreach ($decoded as $index => $field) {
            $place = lang('Models.checkPlace', ['number' => $index + 1]);

            if (! is_array($field) || array_is_list($field)) {
                $errors[] = ['field' => null, 'message' => lang('Models.checkNotObject', ['place' => $place])];
                continue;
            }

            $id = $field['id'] ?? null;
            if (! is_string($id) || $id === '') {
                $errors[] = ['field' => null, 'message' => lang('Models.checkIdMissing', ['place' => $place])];
                continue;
            }
            if (preg_match(self::ID, $id) !== 1) {
                $errors[] = ['field' => $id, 'message' => lang('Models.checkIdBad', ['id' => $id])];
                continue;
            }
            if (isset($seen[$id])) {
                $errors[] = ['field' => $id, 'message' => lang('Models.checkIdTwice', ['id' => $id])];
                continue;
            }
            $seen[$id] = true;

            $problems = [];
            $field    = $this->fieldOf($field, $id, $problems);
            foreach ($problems as $message) {
                $errors[] = ['field' => $id, 'message' => $message];
            }
            $fields[] = $field;
        }

        return ['fields' => $fields, 'errors' => $errors];
    }

    /**
     * The label field of a model, checked against its fields.
     *
     * @param list<array<string, mixed>> $fields
     *
     * @return string|null The message, or null when the label field is fine or empty.
     */
    public function checkLabelField(?string $labelField, array $fields): ?string
    {
        if ($labelField === null || $labelField === '') {
            return null;
        }

        foreach ($fields as $field) {
            if (($field['id'] ?? null) !== $labelField) {
                continue;
            }
            $type = (string) ($field['type'] ?? '');
            $many = AbstractField::flag($field['multiple'] ?? false);
            $secret = $type === 'text' && ($field['format'] ?? '') === 'password';

            return in_array($type, FieldCatalog::NAMING, true) && ! $many && ! $secret ? null : lang('Models.checkLabelType', ['field' => $labelField]);
        }

        return lang('Models.checkLabelMissing', ['field' => $labelField]);
    }

    /**
     * @param array<string, mixed> $field
     * @param list<string>         $problems
     *
     * @return array<string, mixed>
     */
    private function fieldOf(array $field, string $id, array &$problems): array
    {
        $label = $field['label'] ?? $id;
        if (! is_string($label) || trim($label) === '') {
            $label = $id;
        }

        // The keys every definition starts with come first, in the order the converter writes them.
        $rest = $field;
        unset($rest['id'], $rest['label'], $rest['type']);
        $field = ['id' => $id, 'label' => trim($label)] + (array_key_exists('type', $field) ? ['type' => $field['type']] : []) + $rest;

        $typeName = $field['type'] ?? null;
        $type     = is_string($typeName) ? $this->types->get($typeName) : null;
        if ($type === null) {
            $problems[] = lang('Models.checkTypeUnknown', ['id' => $id, 'type' => is_scalar($typeName) ? (string) $typeName : '']);

            return $field;
        }

        foreach ($type->settings() as $setting) {
            $key = (string) $setting['key'];
            if (! array_key_exists($key, $field) || $field[$key] === null || $field[$key] === '') {
                if (! empty($setting['required'])) {
                    $problems[] = lang('Models.checkSettingRequired', ['id' => $id, 'setting' => (string) $setting['label']]);
                }
                unset($field[$key]);
                continue;
            }
            $field[$key] = $this->setting($setting, $field[$key], $id, $problems);
        }

        foreach (['required', 'filterable', 'list'] as $flag) {
            if (array_key_exists($flag, $field) && ! is_bool($field[$flag])) {
                $field[$flag] = AbstractField::flag($field[$flag]);
            }
        }
        if (isset($field['help']) && ! is_string($field['help'])) {
            unset($field['help']);
        }
        if (($field['help'] ?? null) === '') {
            unset($field['help']);
        }
        foreach (['required', 'filterable'] as $flag) {
            if (($field[$flag] ?? false) === false) {
                unset($field[$flag]);
            }
        }

        if (isset($field['min'], $field['max']) && is_numeric($field['min']) && is_numeric($field['max']) && $field['min'] > $field['max']) {
            $problems[] = lang('Models.checkMinMax', ['id' => $id]);
        }

        if (! empty($field['filterable']) && $type->kind($field) === null) {
            $problems[] = lang('Models.checkNotFilterable', ['id' => $id]);
        }

        if (array_key_exists('default', $field) && $field['default'] !== null && $field['default'] !== '' && $field['default'] !== []) {
            $default = $type->normalise($field['default'], $field);
            $message = $type->validate($default, $field);
            if ($message !== null) {
                $problems[] = lang('Models.checkDefault', ['id' => $id, 'message' => $message]);
            } else {
                $field['default'] = $default;
            }
        } else {
            unset($field['default']);
        }

        return $field;
    }

    /**
     * One option of a type, cast to the shape it is stored in.
     *
     * @param array<string, mixed> $setting
     * @param list<string>         $problems
     */
    private function setting(array $setting, mixed $value, string $id, array &$problems): mixed
    {
        $name = (string) $setting['label'];

        switch ($setting['control']) {
            case 'toggle':
                return AbstractField::flag($value);

            case 'select':
                if (! is_string($value) || ! array_key_exists($value, $setting['choices'] ?? [])) {
                    $problems[] = lang('Models.checkSettingChoice', ['id' => $id, 'setting' => $name]);

                    return $value;
                }

                return $value;

            case 'number':
                if (! is_numeric($value)) {
                    $problems[] = lang('Models.checkSettingNumber', ['id' => $id, 'setting' => $name]);

                    return $value;
                }
                $number = $value + 0;
                if (! empty($setting['integer']) && ! is_int($number)) {
                    $problems[] = lang('Models.checkSettingNumber', ['id' => $id, 'setting' => $name]);
                }
                if (isset($setting['min']) && $number < $setting['min']) {
                    $problems[] = lang('Models.checkSettingNumber', ['id' => $id, 'setting' => $name]);
                }

                return $number;

            case 'text':
                return is_string($value) ? trim($value) : (string) $value;

            case 'model':
                if (! is_numeric($value) || ! isset($this->modelIds[(int) $value])) {
                    $problems[] = lang('Models.checkModelMissing', ['id' => $id]);

                    return $value;
                }

                return (int) $value;

            case 'choices':
                return $this->choices($value, $id, $problems);
        }

        return $value;
    }

    /**
     * @param list<string> $problems
     *
     * @return list<array{value: string, label: string}>
     */
    private function choices(mixed $value, string $id, array &$problems): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $problems[] = lang('Models.checkChoicesList', ['id' => $id]);

            return [];
        }

        $choices = [];
        $seen    = [];
        foreach ($value as $choice) {
            $one   = is_array($choice) ? ($choice['value'] ?? null) : null;
            $label = is_array($choice) ? ($choice['label'] ?? null) : null;
            if (! is_scalar($one) || trim((string) $one) === '') {
                $problems[] = lang('Models.checkChoiceValue', ['id' => $id]);
                continue;
            }
            $one = (string) $one;
            if (isset($seen[$one])) {
                $problems[] = lang('Models.checkChoiceTwice', ['id' => $id, 'value' => $one]);
                continue;
            }
            $seen[$one] = true;
            $choices[]  = ['value' => $one, 'label' => is_scalar($label) && trim((string) $label) !== '' ? trim((string) $label) : $one];
        }

        if ($choices === []) {
            $problems[] = lang('Models.checkChoicesEmpty', ['id' => $id]);
        }

        return $choices;
    }
}
