<?php

namespace App\Fields;

/**
 * Several lines of plain text.
 */
class LongTextField extends AbstractField
{
    public function name(): string
    {
        return 'longtext';
    }

    public function normalise(mixed $value, array $definition): mixed
    {
        return is_scalar($value) ? (string) $value : $value;
    }

    protected function check(mixed $value, array $definition): ?string
    {
        return is_string($value) ? null : lang('Fields.notText', ['field' => self::labelOf($definition)]);
    }

    protected function show(mixed $value, array $definition): string
    {
        return esc(self::excerpt($this->plain((string) $value), 100));
    }

    /**
     * The text of a value as a list shows it. A rich text has its markup taken off.
     */
    protected function plain(string $value): string
    {
        return $value;
    }

    public function input(mixed $value, array $definition, ?string $error = null): string
    {
        $attributes = self::inputAttributes($definition, $error) + $this->island($definition);
        $control    = '<textarea class="textarea" rows="' . $this->rows() . '"' . attrs($attributes) . '>' . esc(is_scalar($value) ? (string) $value : '') . '</textarea>';

        return $this->field($definition, $control, $error);
    }

    /**
     * The attributes that hand the box to a script island, which this type has none of.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<string, string>
     */
    protected function island(array $definition): array
    {
        return [];
    }

    protected function rows(): int
    {
        return 5;
    }
    public function fromLegacy(array $old, array $context = []): array
    {
        return $this->build($old, $this->legacyOptions($old), is_string($old['value'] ?? null) ? $old['value'] : null);
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        if ($old === null || is_string($old)) {
            return $old;
        }
        if (is_scalar($old)) {
            return (string) $old;
        }

        throw new ConversionProblem('the value is not text');
    }

    /**
     * The options this type adds to the definition.
     *
     * @param array<string, mixed> $old
     *
     * @return array<string, mixed>
     */
    protected function legacyOptions(array $old): array
    {
        return [];
    }
}
