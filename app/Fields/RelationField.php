<?php

namespace App\Fields;

/**
 * The entry of another model, or several when `multiple` is on, held as the id of the entry. `model` is the id of the
 * model the entries come from. Lists and pickers show the entry by the label field of that model.
 */
final class RelationField extends IdField
{
    /**
     * A target model with more entries than this is picked from by searching, not from a list.
     */
    public const PICKER_FROM = 100;

    public function name(): string
    {
        return 'relation';
    }

    public function settings(): array
    {
        return [
            ['key' => 'model', 'control' => 'model', 'label' => lang('Fields.setModel'), 'default' => null, 'required' => true],
            ['key' => 'multiple', 'control' => 'toggle', 'label' => lang('Fields.setMultipleEntries'), 'default' => false],
        ];
    }

    protected function show(mixed $value, array $definition): string
    {
        $ids = array_filter(is_array($value) ? $value : [$value], 'is_int');
        service('entryLabels')->preload($ids);

        $chips = '';
        foreach ($ids as $id) {
            $chips .= '<span class="chip">' . esc(service('entryLabels')->label($id) ?? ('#' . $id)) . '</span>';
        }

        return '<div class="chips">' . $chips . '</div>';
    }

    public function input(mixed $value, array $definition, ?string $error = null): string
    {
        $many    = self::flag($definition['multiple'] ?? false);
        $model   = (int) ($definition['model'] ?? 0);

        // A model with many entries is not a list to choose from. The box then holds only the chosen entries, and the
        // picker script (`ui/picker.js`) searches the rest by label.
        $picker  = (service('entryStore')->countsByModel()[$model] ?? 0) > self::PICKER_FROM;
        $options = $picker ? [] : service('entryLabels')->options($model);
        $picked  = array_map('strval', array_filter(is_array($value) ? $value : [$value], 'is_int'));
        // A chosen entry that is no longer in the first options is kept, so saving does not drop it.
        foreach ($picked as $id) {
            $options[(int) $id] ??= service('entryLabels')->label((int) $id) ?? ('#' . $id);
        }

        $html = $many || self::flag($definition['required'] ?? false) ? '' : '<option value="">' . esc(lang('Fields.chooseOne')) . '</option>';
        foreach ($options as $id => $label) {
            $html .= '<option value="' . $id . '"' . (in_array((string) $id, $picked, true) ? ' selected' : '') . '>' . esc($label) . '</option>';
        }

        $extra = $picker ? [
            'data-picker'      => base_url('admin/picker/' . $model),
            'data-placeholder' => lang('Fields.pickerSearch'),
            'data-none'        => lang('Fields.pickerNone'),
            'data-more'        => lang('Fields.pickerMore'),
            'data-remove'      => lang('Fields.pickerRemove'),
        ] : [];

        return $this->field($definition, '<select class="select"' . attrs(self::inputAttributes($definition, $error, $many) + ['multiple' => $many] + $extra) . '>' . $html . '</select>', $error);
    }

    public function filters(array $definition): array
    {
        return self::flag($definition['multiple'] ?? false) ? [] : [['op' => 'eq', 'label' => lang('Fields.filterIs'), 'control' => 'select', 'choices' => array_map('strval', service('entryLabels')->options((int) ($definition['model'] ?? 0)))]];
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
