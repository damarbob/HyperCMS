<?php

namespace App\Fields;

/**
 * A colour as `#rrggbb`.
 */
final class ColorField extends AbstractField
{
    public function name(): string
    {
        return 'color';
    }

    public function normalise(mixed $value, array $definition): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return is_string($value) && self::valid(trim($value)) ? strtolower(trim($value)) : $value;
    }

    public function kind(array $definition): ?string
    {
        return 'string';
    }

    public function sortKey(mixed $value): string|float|null
    {
        return self::textKey($value);
    }

    protected function check(mixed $value, array $definition): ?string
    {
        return is_string($value) && self::valid($value) ? null : lang('Fields.notColor', ['field' => self::labelOf($definition)]);
    }

    protected function show(mixed $value, array $definition): string
    {
        $text = esc((string) $value);

        return is_string($value) && self::valid($value)
            ? '<span class="swatch" style="background:' . attr($value) . '"></span><span class="mono">' . $text . '</span>'
            : $text;
    }

    public function input(mixed $value, array $definition, ?string $error = null): string
    {
        $color   = is_string($value) && self::valid($value) ? $value : (is_string($definition['default'] ?? null) ? $definition['default'] : '#000000');
        $control = '<input class="input color" type="color" value="' . attr($color) . '"' . attrs(self::inputAttributes($definition, $error)) . '>';

        return $this->field($definition, $control, $error);
    }

    public function filters(array $definition): array
    {
        return [['op' => 'eq', 'label' => lang('Fields.filterIs'), 'control' => 'text']];
    }
    public function fromLegacy(array $old, array $context = []): array
    {
        $default = is_string($old['value'] ?? null) && self::valid($old['value']) ? strtolower($old['value']) : null;

        return $this->build($old, [], $default);
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        if ($old === null || (is_string($old) && trim($old) === '')) {
            return null;
        }
        if (is_string($old) && self::valid(trim($old))) {
            return strtolower(trim($old));
        }

        throw new ConversionProblem('"' . self::shown($old) . '" is not a colour');
    }

    private static function valid(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }
}
