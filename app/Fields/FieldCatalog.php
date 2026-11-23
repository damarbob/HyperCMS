<?php

namespace App\Fields;

/**
 * What the model builder needs to know about the field types: their names, their options, whether a field can be
 * filterable, and the defaults the builder writes. The builder reads it as JSON, so a type added by a module shows up
 * without a change to the builder.
 */
final class FieldCatalog
{
    /**
     * Types that are not a column of a list unless the definition says `list`. Their values are long or not for people.
     */
    public const QUIET = ['longtext', 'richtext', 'code', 'hidden'];

    /**
     * Types a model can use as the name of its entries, when they hold one plain value.
     */
    public const NAMING = ['text', 'longtext', 'number', 'datetime', 'color', 'choice', 'hook'];

    public function __construct(private readonly FieldTypes $types)
    {
    }

    /**
     * @param list<array{id: int, name: string}> $models The models a relation can point at.
     *
     * @return array{types: list<array<string, mixed>>, models: list<array{id: int, name: string}>, naming: list<string>}
     */
    public function forBuilder(array $models): array
    {
        $types = [];
        foreach ($this->types->names() as $name) {
            $type = $this->types->get($name);
            if ($type === null) {
                continue;
            }

            $settings = [];
            foreach ($type->settings() as $setting) {
                if (isset($setting['choices'])) {
                    $choices = [];
                    foreach ($setting['choices'] as $value => $label) {
                        $choices[] = ['value' => (string) $value, 'label' => (string) $label];
                    }
                    $setting['choices'] = $choices;
                }
                $settings[] = $setting;
            }

            // Whether the field can be filterable depends on its options, so the two answers that matter are asked here.
            $one  = $type->kind(['type' => $name, 'multiple' => false]) !== null;
            $many = $type->kind(['type' => $name, 'multiple' => true]) !== null;

            $types[] = [
                'name'        => $name,
                'label'       => self::label($name),
                'settings'    => $settings,
                'listDefault' => ! in_array($name, self::QUIET, true),
                'filterable'  => ! $one ? 'never' : ($many ? 'always' : 'single'),
            ];
        }

        return ['types' => $types, 'models' => $models, 'naming' => self::NAMING];
    }

    /**
     * The name of a type for a person. A type of a module that has no message shows its own name.
     */
    public static function label(string $name): string
    {
        $key  = 'Fields.type' . ucfirst($name);
        $text = lang($key);

        return $text === $key ? $name : $text;
    }
}
