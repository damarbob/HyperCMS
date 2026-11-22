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

    protected function show(mixed $value, array $definition): string
    {
        return esc(self::names()[(int) $value] ?? ('#' . (int) $value));
    }

    public function input(mixed $value, array $definition, ?string $error = null): string
    {
        $html = self::flag($definition['required'] ?? false) ? '' : '<option value="">' . esc(lang('Fields.chooseOne')) . '</option>';
        foreach (self::names() as $id => $name) {
            $html .= '<option value="' . $id . '"' . ((int) $value === $id ? ' selected' : '') . '>' . esc($name) . '</option>';
        }

        return $this->field($definition, '<select class="select"' . attrs(self::inputAttributes($definition, $error)) . '>' . $html . '</select>', $error);
    }

    public function filters(array $definition): array
    {
        return [['op' => 'eq', 'label' => lang('Fields.filterIs'), 'control' => 'select', 'choices' => array_map('strval', self::names())]];
    }

    /**
     * The name of every live model by id.
     *
     * @return array<int, string>
     */
    private static function names(): array
    {
        $names = [];
        foreach (service('modelStore')->all() as $model) {
            $names[(int) $model['id']] = (string) $model['name'];
        }

        return $names;
    }
    public function fromLegacy(array $old, array $context = []): array
    {
        if (LegacyQuery::classify($old['options'] ?? null)['type'] !== 'model') {
            throw new ConversionProblem('the data query is not a query on models');
        }

        return $this->build($old, [], is_scalar($old['value'] ?? null) ? self::idFrom($old['value']) : null);
    }
}
