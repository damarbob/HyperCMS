<?php

namespace App\Fields;

/**
 * A date and a time without a zone, stored as `YYYY-MM-DD HH:MM:SS` in the local time of whoever typed it.
 */
final class DatetimeField extends AbstractField
{
    public function name(): string
    {
        return 'datetime';
    }

    public function normalise(mixed $value, array $definition): mixed
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            return $value;
        }

        $text = trim($value);
        // The browser's empty value and the zero date an old form could send mean no date.
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return null;
        }

        return self::parse($text) ?? $value;
    }

    public function kind(array $definition): ?string
    {
        return 'datetime';
    }

    public function sortKey(mixed $value): string|float|null
    {
        return is_string($value) ? self::parse($value) : null;
    }

    protected function check(mixed $value, array $definition): ?string
    {
        return is_string($value) && self::parse($value) === $value
            ? null
            : lang('Fields.notDate', ['field' => self::labelOf($definition), 'value' => self::shown($value)]);
    }

    protected function show(mixed $value, array $definition): string
    {
        $parsed = is_string($value) ? self::parse($value) : null;
        if ($parsed === null) {
            return esc(is_scalar($value) ? (string) $value : '');
        }

        $time      = strtotime(substr($parsed, 0, 19) . ' UTC');
        $formatter = class_exists(IntlDateFormatter::class) ? IntlDateFormatter::create(service('request')->getLocale(), IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC') : null;
        $text      = $formatter !== null && $time !== false ? $formatter->format($time) : substr($parsed, 0, 16);

        return '<time datetime="' . attr(str_replace(' ', 'T', substr($parsed, 0, 19))) . '">' . esc((string) $text) . '</time>';
    }

    public function input(mixed $value, array $definition, ?string $error = null): string
    {
        $parsed = is_string($value) ? self::parse($value) : null;
        $local  = '';
        $step   = null;
        if ($parsed !== null) {
            // The box shows seconds only when they are not zero, so a stored value is not changed by opening and saving it.
            $local = str_replace(' ', 'T', substr($parsed, 0, 19));
            if (str_ends_with($local, ':00')) {
                $local = substr($local, 0, 16);
            } else {
                $step = 1;
            }
        }

        $attributes = self::inputAttributes($definition, $error) + ['step' => $step];
        $control    = '<input class="input" type="datetime-local" value="' . attr($local) . '"' . attrs($attributes) . '>';

        return $this->field($definition, $control, $error);
    }

    public function filters(array $definition): array
    {
        return [
            ['op' => 'gte', 'label' => lang('Fields.filterFrom'), 'control' => 'datetime-local'],
            ['op' => 'lte', 'label' => lang('Fields.filterTo'), 'control' => 'datetime-local'],
        ];
    }
    public function fromLegacy(array $old, array $context = []): array
    {
        $default = is_string($old['value'] ?? null) ? self::parse($old['value']) : null;

        return $this->build($old, [], $default);
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        $read = $this->normalise($old, $definition);
        if ($read !== null && (! is_string($read) || self::parse($read) !== $read)) {
            throw new ConversionProblem('"' . self::shown($old) . '" is not a date and time');
        }

        return $read;
    }

    /**
     * Read `2026-01-05T09:30`, `2026-01-05 09:30:00` and the same with a fraction of a second, and return the
     * stored form, or null when the text is not a real date and time.
     */
    private static function parse(string $text): ?string
    {
        $pattern = '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2})(\.\d{1,6})?)?$/';
        if (preg_match($pattern, trim($text), $m) !== 1
            || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            || (int) $m[4] > 23 || (int) $m[5] > 59 || (int) ($m[6] ?? 0) > 59) {
            return null;
        }

        return sprintf('%s-%s-%s %s:%s:%s%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6] ?? '00', $m[7] ?? '');
    }
}
