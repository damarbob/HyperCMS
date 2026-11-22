<?php

namespace App\Fields;

use App\Data\Entries\FilterableFields;

/**
 * One line of text, with a `format` of `plain`, `email`, `url` or `password`.
 */
final class TextField extends AbstractField
{
    public const FORMATS = ['plain', 'email', 'url', 'password'];

    public function name(): string
    {
        return 'text';
    }

    public function normalise(mixed $value, array $definition): mixed
    {
        return is_scalar($value) ? (string) $value : $value;
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
        if (! is_string($value)) {
            return lang('Fields.notText', ['field' => self::labelOf($definition)]);
        }
        if (self::flag($definition['filterable'] ?? false) && mb_strlen($value) > FilterableFields::MAX_STRING) {
            return lang('Fields.tooLong', ['field' => self::labelOf($definition), 'max' => FilterableFields::MAX_STRING]);
        }

        return match ($definition['format'] ?? 'plain') {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? lang('Fields.notEmail', ['field' => self::labelOf($definition)]) : null,
            'url'   => filter_var($value, FILTER_VALIDATE_URL) === false ? lang('Fields.notUrl', ['field' => self::labelOf($definition)]) : null,
            default => null,
        };
    }

    public function fromLegacy(array $old, array $context = []): array
    {
        $format = in_array($old['type'] ?? null, ['email', 'url', 'password'], true) ? $old['type'] : 'plain';

        return $this->build(
            $old,
            $format === 'plain' ? [] : ['format' => $format],
            is_string($old['value'] ?? null) ? $old['value'] : null,
        );
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
