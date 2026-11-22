<?php

namespace App\Fields;

/**
 * A model of HyperCMS, held as the id of the model.
 */
final class ModelField extends IdField
{
    public function name(): string
    {
        return 'model';
    }

    public function normalise(mixed $value, array $definition): mixed
    {
        return parent::normalise($value, ['multiple' => false] + $definition);
    }

    public function fromLegacy(array $old, array $context = []): array
    {
        if (LegacyQuery::classify($old['options'] ?? null)['type'] !== 'model') {
            throw new ConversionProblem('the data query is not a query on models');
        }

        return $this->build($old, [], is_scalar($old['value'] ?? null) ? self::idFrom($old['value']) : null);
    }
}
