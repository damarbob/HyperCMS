<?php

namespace App\Fields;

/**
 * What the fields that hold the id of a row share: one id or, when `multiple` is on, a list of ids.
 */
abstract class IdField extends AbstractField
{
    public function normalise(mixed $value, array $definition): mixed
    {
        if (self::flag($definition['multiple'] ?? false)) {
            if ($value === null || $value === '') {
                return [];
            }
            if (is_array($value) && array_is_list($value)) {
                $ids = array_map(self::idFrom(...), $value);

                return in_array(null, $ids, true) ? $value : $ids;
            }

            return [self::idFrom($value) ?? $value];
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return self::idFrom($value) ?? $value;
    }

    public function kind(array $definition): ?string
    {
        return self::flag($definition['multiple'] ?? false) ? null : 'int';
    }

    public function sortKey(mixed $value): string|float|null
    {
        return is_int($value) ? (float) $value : null;
    }

    protected function check(mixed $value, array $definition): ?string
    {
        $label = self::labelOf($definition);
        if (self::flag($definition['multiple'] ?? false)) {
            if (! is_array($value) || ! array_is_list($value)) {
                return lang('Fields.notList', ['field' => $label]);
            }
            foreach ($value as $id) {
                if (! is_int($id) || $id < 1) {
                    return lang('Fields.notAnId', ['field' => $label, 'value' => self::shown($id)]);
                }
            }

            return null;
        }

        return is_int($value) && $value >= 1 ? null : lang('Fields.notAnId', ['field' => $label, 'value' => self::shown($value)]);
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        if (self::flag($definition['multiple'] ?? false)) {
            if ($old === null || $old === '') {
                return [];
            }

            return array_map(self::legacyId(...), is_array($old) && array_is_list($old) ? $old : [$old]);
        }

        return $old === null || (is_string($old) && trim($old) === '') ? null : self::legacyId($old);
    }

    /**
     * A positive id from an int or from digits, or null.
     */
    protected static function idFrom(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 1 ? $value : null;
        }
        if (is_string($value) && preg_match('/^\s*[0-9]{1,18}\s*$/', $value) === 1 && (int) $value >= 1) {
            return (int) $value;
        }

        return null;
    }

    private static function legacyId(mixed $value): int
    {
        $id = self::idFrom($value);
        if ($id === null) {
            throw new ConversionProblem('"' . self::shown($value) . '" is not an id');
        }

        return $id;
    }
}
