<?php

namespace App\Fields;

/**
 * A yes or no, stored as `true` or `false`. When it is required, it must be true, as a box to tick is.
 *
 * StarDust indexes text, whole numbers, decimals and dates, so a boolean cannot be filterable yet.
 */
final class BooleanField extends AbstractField
{
    private const ON  = ['on', 'true', '1', 'yes'];
    private const OFF = ['off', 'false', '0', 'no', ''];

    public function name(): string
    {
        return 'boolean';
    }

    public function normalise(mixed $value, array $definition): mixed
    {
        return self::read($value) ?? $value;
    }

    public function sortKey(mixed $value): string|float|null
    {
        $read = self::read($value);

        return $read === null ? null : ($read ? 1.0 : 0.0);
    }

    public function validate(mixed $value, array $definition): ?string
    {
        if (! is_bool($value)) {
            return lang('Fields.notBoolean', ['field' => self::labelOf($definition)]);
        }

        return $value === false && self::flag($definition['required'] ?? false)
            ? lang('Fields.required', ['field' => self::labelOf($definition)])
            : null;
    }

    public function fromLegacy(array $old, array $context = []): array
    {
        $on = self::read($old['checked'] ?? null) === true || self::read($old['value'] ?? null) === true;

        return $this->build($old, [], $on ? true : null);
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        $read = self::read($old);
        if ($read === null) {
            throw new ConversionProblem('"' . self::shown($old) . '" is not a yes or a no');
        }

        return $read;
    }

    /**
     * Read what the old form and a person would write as on or off. A missing value is off.
     */
    private static function read(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null) {
            return false;
        }
        if (is_int($value) || is_float($value)) {
            return $value == 1 ? true : ($value == 0 ? false : null);
        }
        if (is_string($value)) {
            $text = strtolower(trim($value));
            if (in_array($text, self::ON, true)) {
                return true;
            }
            if (in_array($text, self::OFF, true)) {
                return false;
            }
        }

        return null;
    }
}
