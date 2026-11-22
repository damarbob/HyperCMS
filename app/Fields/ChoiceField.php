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

    protected function show(mixed $value, array $definition): string
    {
        $labels = self::labels($definition);
        $chips  = '';
        foreach (is_array($value) ? $value : [$value] as $item) {
            $key    = is_scalar($item) ? (string) $item : '';
            $chips .= '<span class="chip">' . esc($labels[$key] ?? $key) . '</span>';
        }

        return '<div class="chips">' . $chips . '</div>';
    }

    public function input(mixed $value, array $definition, ?string $error = null): string
    {
        $many   = self::flag($definition['multiple'] ?? false);
        $widget = in_array($definition['widget'] ?? null, self::WIDGETS, true) ? $definition['widget'] : ($many ? 'checkboxes' : 'select');
        $picked = array_map('strval', array_filter(is_array($value) ? $value : [$value], 'is_scalar'));
        $labels = self::labels($definition);

        if ($widget === 'select') {
            $attributes = self::inputAttributes($definition, $error, $many) + ['multiple' => $many];
            $options    = $many || self::flag($definition['required'] ?? false) ? '' : '<option value="">' . esc(lang('Fields.chooseOne')) . '</option>';
            foreach ($labels as $key => $label) {
                $options .= '<option value="' . attr((string) $key) . '"' . (in_array((string) $key, $picked, true) ? ' selected' : '') . '>' . esc($label) . '</option>';
            }

            return $this->field($definition, '<select class="select"' . attrs($attributes) . '>' . $options . '</select>', $error);
        }

        // Radio buttons and check boxes are several inputs that share a name and a label.
        $type       = $widget === 'radio' && ! $many ? 'radio' : 'checkbox';
        $attributes = self::inputAttributes($definition, $error, $type === 'checkbox');
        $rows       = '';
        $index      = 0;
        foreach ($labels as $key => $label) {
            $one = ['id' => $attributes['id'] . '-' . $index++, 'value' => (string) $key, 'checked' => in_array((string) $key, $picked, true)] + array_diff_key($attributes, ['id' => 1]);
            $rows .= '<label class="check"><input type="' . $type . '"' . attrs($one) . '>' . esc($label) . '</label>';
        }

        // An empty list of check boxes posts nothing, so a hidden marker says the field was on the form.
        $marker = $type === 'checkbox' ? '<input type="hidden" name="' . attr((string) $attributes['name']) . '" value="">' : '';

        return $this->field($definition, $marker . '<div class="options">' . $rows . '</div>', $error, true);
    }

    public function filters(array $definition): array
    {
        return self::flag($definition['multiple'] ?? false) ? [] : [['op' => 'eq', 'label' => lang('Fields.filterIs'), 'control' => 'select', 'choices' => self::labels($definition)]];
    }

    /**
     * The choices of a definition as label by value.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<string, string>
     */
    private static function labels(array $definition): array
    {
        $labels = [];
        foreach (is_array($definition['choices'] ?? null) ? $definition['choices'] : [] as $choice) {
            if (is_array($choice) && is_scalar($choice['value'] ?? null)) {
                $labels[(string) $choice['value']] = is_scalar($choice['label'] ?? null) ? (string) $choice['label'] : (string) $choice['value'];
            }
        }

        return $labels;
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
