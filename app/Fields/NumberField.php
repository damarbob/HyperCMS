<?php

namespace App\Fields;

/**
 * A number, typed in a box or moved on a slider (`widget`), whole when `integer` is on, and held between `min` and `max`.
 */
final class NumberField extends AbstractField
{
    public function name(): string
    {
        return 'number';
    }

    public function normalise(mixed $value, array $definition): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $number = self::numberFrom($value);
        if ($number === null) {
            return $value;
        }
        if (self::flag($definition['integer'] ?? false) && is_float($number) && floor($number) === $number && abs($number) < 9.2e18) {
            return (int) $number;
        }

        return $number;
    }

    public function kind(array $definition): ?string
    {
        return self::flag($definition['integer'] ?? false) ? 'int' : 'numeric';
    }

    public function sortKey(mixed $value): string|float|null
    {
        $number = self::numberFrom($value);

        return $number === null ? null : (float) $number;
    }

    protected function check(mixed $value, array $definition): ?string
    {
        $label = self::labelOf($definition);
        if (! is_int($value) && ! is_float($value)) {
            return lang('Fields.notNumber', ['field' => $label, 'value' => self::shown($value)]);
        }
        if (self::flag($definition['integer'] ?? false) && ! is_int($value)) {
            return lang('Fields.notInteger', ['field' => $label, 'value' => self::shown($value)]);
        }
        if (isset($definition['min']) && $value < $definition['min']) {
            return lang('Fields.belowMin', ['field' => $label, 'min' => $definition['min']]);
        }
        if (isset($definition['max']) && $value > $definition['max']) {
            return lang('Fields.aboveMax', ['field' => $label, 'max' => $definition['max']]);
        }

        return null;
    }

    public function fromLegacy(array $old, array $context = []): array
    {
        $slider = ($old['type'] ?? null) === 'range';
        $step   = null;
        $min    = null;
        $max    = null;

        if ($slider) {
            // A slider took its limits from the first item of a list, and drew 0 to 100 without one.
            $limits = is_array($old['options'] ?? null) ? ($old['options'][0] ?? []) : [];
            $limits = is_array($limits) ? $limits : [];
            $min    = self::numberFrom($limits['min'] ?? null) ?? 0;
            $max    = self::numberFrom($limits['max'] ?? null) ?? 100;
        } elseif (is_array($old['options'] ?? null)) {
            $step = self::numberFrom($old['options']['step'] ?? null);
            $min  = self::numberFrom($old['options']['min'] ?? null);
            $max  = self::numberFrom($old['options']['max'] ?? null);
        }

        // A step of 1 is what made the old field an integer, so the step is not kept beside it.
        $integer = $step !== null && (float) $step === 1.0;
        $options = ['integer' => $integer];
        foreach (['min' => $min, 'max' => $max, 'step' => $integer ? null : $step] as $key => $number) {
            if ($number !== null) {
                $options[$key] = $number;
            }
        }
        $options['widget'] = $slider ? 'slider' : 'input';

        return $this->build($old, $options, self::numberFrom($old['value'] ?? null));
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        if ($old === null || (is_string($old) && trim($old) === '')) {
            return null;
        }

        $number = self::numberFrom($old);
        if ($number === null) {
            throw new ConversionProblem('"' . self::shown($old) . '" is not a number');
        }
        if (self::flag($definition['integer'] ?? false)) {
            if (is_float($number) && floor($number) === $number && abs($number) < 9.2e18) {
                return (int) $number;
            }
            if (! is_int($number)) {
                throw new ConversionProblem('"' . self::shown($old) . '" is not a whole number');
            }
        }

        return $number;
    }
}
