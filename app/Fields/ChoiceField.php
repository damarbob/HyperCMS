<?php

namespace App\Fields;

/**
 * One value, or several when `multiple` is on, picked from `choices`, each a `value` and a `label`. The `widget`
 * is `select`, `radio` or `checkboxes`.
 */
final class ChoiceField extends AbstractField
{
    public const WIDGETS = ['select', 'radio', 'checkboxes'];

    public function name(): string
    {
        return 'choice';
    }

    public function normalise(mixed $value, array $definition): mixed
    {
        if (self::flag($definition['multiple'] ?? false)) {
            if ($value === null || $value === '') {
                return [];
            }
            if (is_scalar($value)) {
                return [(string) $value];
            }
            if (is_array($value) && array_is_list($value) && array_filter($value, 'is_scalar') === $value) {
                return array_map('strval', $value);
            }

            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : $value;
    }

    public function kind(array $definition): ?string
    {
        return self::flag($definition['multiple'] ?? false) ? null : 'string';
    }

    public function sortKey(mixed $value): string|float|null
    {
        return self::textKey($value);
    }

    protected function check(mixed $value, array $definition): ?string
    {
        $label   = self::labelOf($definition);
        $allowed = self::values($definition);

        if (self::flag($definition['multiple'] ?? false)) {
            if (! is_array($value) || ! array_is_list($value)) {
                return lang('Fields.notList', ['field' => $label]);
            }
            $picked = $value;
        } else {
            if (! is_string($value)) {
                return lang('Fields.notOneValue', ['field' => $label]);
            }
            $picked = [$value];
        }

        foreach ($picked as $item) {
            if (! is_string($item) || ($allowed !== [] && ! in_array($item, $allowed, true))) {
                return lang('Fields.notAChoice', ['field' => $label, 'value' => self::shown($item)]);
            }
        }

        return null;
    }

    public function fromLegacy(array $old, array $context = []): array
    {
        $type = $old['type'] ?? null;
        if (! is_array($old['options'] ?? null) || ! array_is_list($old['options'])) {
            throw new ConversionProblem('the options of a ' . (is_string($type) ? $type : 'choice') . ' field are not a list of choices');
        }

        $multiple = $type === 'checkboxes';
        $choices  = [];
        $checked  = [];
        foreach ($old['options'] as $option) {
            if (! is_array($option) || ! isset($option['value']) || ! is_scalar($option['value'])) {
                continue;
            }
            $value     = (string) $option['value'];
            $choices[] = ['value' => $value, 'label' => is_scalar($option['label'] ?? null) && (string) $option['label'] !== '' ? (string) $option['label'] : $value];
            if (self::flag($option['checked'] ?? false)) {
                $checked[] = $value;
            }
        }

        if ($multiple) {
            $default = $checked;
        } elseif ($type === 'radio') {
            $default = $checked[0] ?? null;
        } else {
            $default = is_scalar($old['value'] ?? null) ? (string) $old['value'] : null;
        }

        return $this->build(
            $old,
            [
                'multiple' => $multiple,
                'widget'   => in_array($type, self::WIDGETS, true) ? $type : 'select',
                'choices'  => $choices,
            ],
            $default,
        );
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        if (self::flag($definition['multiple'] ?? false)) {
            if ($old === null || $old === '') {
                return [];
            }
            if (is_scalar($old)) {
                return [(string) $old];
            }
            if (is_array($old) && array_is_list($old) && array_filter($old, 'is_scalar') === $old) {
                return array_map('strval', $old);
            }

            throw new ConversionProblem('the value is not a list of choices');
        }

        if ($old === null || $old === '') {
            return null;
        }
        if (is_scalar($old)) {
            return (string) $old;
        }

        throw new ConversionProblem('the value is a list, and the field takes one choice');
    }

    /**
     * The values a definition offers.
     *
     * @param array<string, mixed> $definition
     *
     * @return list<string>
     */
    private static function values(array $definition): array
    {
        $values = [];
        foreach (is_array($definition['choices'] ?? null) ? $definition['choices'] : [] as $choice) {
            if (is_array($choice) && is_scalar($choice['value'] ?? null)) {
                $values[] = (string) $choice['value'];
            }
        }

        return $values;
    }
}
