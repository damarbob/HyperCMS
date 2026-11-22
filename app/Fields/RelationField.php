<?php

namespace App\Fields;

/**
 * The entry of another model, or several when `multiple` is on, held as the id of the entry. `model` is the id of the
 * model the entries come from. Lists and pickers show the entry by the label field of that model.
 */
final class RelationField extends IdField
{
    public function name(): string
    {
        return 'relation';
    }

    public function fromLegacy(array $old, array $context = []): array
    {
        $query = LegacyQuery::classify($old['options'] ?? null, $context['modelIds'] ?? []);
        if ($query['type'] !== 'relation') {
            throw new ConversionProblem('the data query is not a query on entries');
        }

        return $this->build($old, ['model' => $query['model'], 'multiple' => false], is_scalar($old['value'] ?? null) ? self::idFrom($old['value']) : null);
    }

    /**
     * The field of the target model that the old query showed as the label, for the label field of that model.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $context
     */
    public static function legacyLabelField(array $old, array $context = []): ?string
    {
        try {
            $query = LegacyQuery::classify($old['options'] ?? null, $context['modelIds'] ?? []);
        } catch (ConversionProblem) {
            return null;
        }

        return $query['type'] === 'relation' ? $query['label'] : null;
    }
}
