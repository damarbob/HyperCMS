<?php

namespace App\Fields;

/**
 * A hook of HyperCMS, held as the name of the hook. `group` limits the choice to the hooks of one group.
 */
final class HookField extends AbstractField
{
    public function name(): string
    {
        return 'hook';
    }

    public function normalise(mixed $value, array $definition): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

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
        return is_string($value) ? null : lang('Fields.notText', ['field' => self::labelOf($definition)]);
    }

    public function input(mixed $value, array $definition, ?string $error = null): string
    {
        $group = is_string($definition['group'] ?? null) ? $definition['group'] : null;
        $html  = self::flag($definition['required'] ?? false) ? '' : '<option value="">' . esc(lang('Fields.chooseOne')) . '</option>';
        foreach (self::options($group) as $name => $label) {
            $html .= '<option value="' . attr($name) . '"' . ($value === $name ? ' selected' : '') . '>' . esc($label) . '</option>';
        }

        return $this->field($definition, '<select class="select"' . attrs(self::inputAttributes($definition, $error)) . '>' . $html . '</select>', $error);
    }

    public function filters(array $definition): array
    {
        return [['op' => 'eq', 'label' => lang('Fields.filterIs'), 'control' => 'text']];
    }

    /**
     * The hooks of the system as label by name, optionally of one group.
     *
     * @return array<string, string>
     */
    private static function options(?string $group): array
    {
        $options = [];
        foreach (dump_hooks($group) as $hooks) {
            foreach ($hooks as $hook) {
                if ($hook instanceof StarCore\Star\HyperHook) {
                    $options[$hook->getName()] = $hook->getLabel();
                }
            }
        }

        return $options;
    }
    public function fromLegacy(array $old, array $context = []): array
    {
        $query = LegacyQuery::classify($old['options'] ?? null);
        if ($query['type'] !== 'hook') {
            throw new ConversionProblem('the data query is not a query on hooks');
        }

        return $this->build($old, $query['group'] === null ? [] : ['group' => $query['group']]);
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        if ($old === null || $old === '') {
            return null;
        }
        if (is_scalar($old)) {
            return (string) $old;
        }

        throw new ConversionProblem('the value is not a hook name');
    }
}
