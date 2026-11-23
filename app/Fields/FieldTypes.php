<?php

namespace App\Fields;

/**
 * The field types HyperCMS knows, by name. Core registers its own, and a module adds more through the module
 * contract. This is the one place that maps a type name to what is known about the type.
 */
final class FieldTypes
{
    /**
     * @var array<string, FieldType>
     */
    private array $types = [];

    public function __construct(FieldType ...$types)
    {
        foreach ($types as $type) {
            $this->register($type);
        }
    }

    /**
     * The types of core, and the ones the loaded modules add.
     */
    public static function core(): self
    {
        $registry = new self(
            new TextField(),
            new LongTextField(),
            new RichTextField(),
            new CodeField(),
            new NumberField(),
            new BooleanField(),
            new ChoiceField(),
            new DatetimeField(),
            new ColorField(),
            new FileField(),
            new RelationField(),
            new ModelField(),
            new HookField(),
            new HiddenField(),
        );

        // A script that runs without the framework, such as the upgrade test, has no modules to ask.
        if (function_exists('service')) {
            foreach (service('modules')->fieldTypes() as $type) {
                $registry->register($type);
            }
        }

        return $registry;
    }

    public function register(FieldType $type): void
    {
        $this->types[$type->name()] = $type;
    }

    public function get(string $name): ?FieldType
    {
        return $this->types[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->types);
    }

    /**
     * The version 2 type that an old field becomes.
     *
     * A field the old form drew as a list of options from a data query becomes `model`, `relation` or `hook` when the
     * query is one of the three that convert, and any other query leaves it `text`.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $context
     *
     * @throws ConversionProblem When the type is unknown, or the query cannot be converted. The message says why.
     */
    public function legacyType(array $old, array $context = []): string
    {
        $type = $old['type'] ?? null;
        if (! is_string($type) || $type === '') {
            throw new ConversionProblem('the field has no type');
        }

        if (LegacyQuery::isQuery($old['options'] ?? null)) {
            return LegacyQuery::classify($old['options'], $context['modelIds'] ?? [])['type'];
        }

        $classes = is_string($old['className'] ?? null) ? preg_split('/\s+/', trim($old['className'])) : [];

        return match ($type) {
            'text', 'email', 'password' => 'text',
            'url'                       => in_array('hyper-file-browse-field', $classes, true) ? 'file' : 'text',
            'textarea'                  => in_array('hyper-rich-text-field', $classes, true)
                ? 'richtext'
                : (in_array('hyper-code-field', $classes, true) ? 'code' : 'longtext'),
            'number', 'range'           => 'number',
            'checkbox'                  => 'boolean',
            'select', 'radio', 'checkboxes' => 'choice',
            'datetime-local'            => 'datetime',
            'color'                     => 'color',
            'file'                      => 'file',
            'hidden'                    => 'hidden',
            default                     => throw new ConversionProblem('the type "' . $type . '" is not known, and the old form drew nothing for it'),
        };
    }

    /**
     * Convert one field of the old definition.
     *
     * A field that cannot be converted becomes a text field that keeps its id, label and help, and the reason is
     * returned, so the upgrade can list it. Only a field without an id returns no field.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $context `modelIds` maps a model name to its id.
     *
     * @return array{field: array<string, mixed>|null, problem: string|null, labelField: string|null}
     */
    public function fromLegacy(array $old, array $context = []): array
    {
        try {
            $name  = $this->legacyType($old, $context);
            $field = $this->require($name)->fromLegacy($old, $context);

            return ['field' => $field, 'problem' => null, 'labelField' => $name === 'relation' ? RelationField::legacyLabelField($old, $context) : null];
        } catch (ConversionProblem $problem) {
            try {
                $text = $this->require('text')->fromLegacy(['type' => 'text'] + $old);
            } catch (ConversionProblem) {
                return ['field' => null, 'problem' => $problem->getMessage(), 'labelField' => null];
            }

            return ['field' => $text, 'problem' => $problem->getMessage(), 'labelField' => null];
        }
    }

    private function require(string $name): FieldType
    {
        return $this->get($name) ?? throw new ConversionProblem('there is no field type ' . $name);
    }
}
