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
