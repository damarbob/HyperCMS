<?php

namespace App\Fields;

/**
 * A value the person does not see, held as text. The admin form never saved a hidden field, so a value comes from
 * `default`, from a module, or from the API.
 */
final class HiddenField extends AbstractField
{
    public function name(): string
    {
        return 'hidden';
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
        return $this->build($old, [], is_scalar($old['value'] ?? null) ? (string) $old['value'] : null);
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
}
