<?php

namespace App\Fields;

/**
 * Everything HyperCMS knows about one type of field: how a posted value is read, checked and ordered, which StarDust
 * type indexes it, and how a field and its values are read from the old format.
 *
 * A definition is one entry of `hyper_models.fields`, for example `{"id":"views","label":"Views","type":"number"}`.
 * A value is in the shape that type is stored in, for example `12.5` for a number and `["news","tech"]` for a
 * choice of several. A value that is missing, or null, reaches every method as null.
 */
interface FieldType
{
    /**
     * The `type` of a definition this class handles, such as `number`.
     */
    public function name(): string;

    /**
     * The options of this type that the model builder lets a person set, besides the keys every type shares.
     *
     * Each one is its `key` in the definition, the `control` to draw (`select`, `toggle`, `number`, `text`, `choices` or
     * `model`), a `label`, and the `default` the builder writes when a field of this type is made. A `select` also has
     * its `choices` as value to label. An option with `omitDefault` is left out of the definition while it holds its
     * default, as `format` is for a plain text.
     *
     * @return list<array<string, mixed>>
     */
    public function settings(): array;

    /**
     * Turn posted input into the stored shape. It never refuses. A value it cannot read is returned as it came, and
     * `validate()` reports it.
     *
     * @param array<string, mixed> $definition
     */
    public function normalise(mixed $value, array $definition): mixed;

    /**
     * Check a normalised value against its definition.
     *
     * @param array<string, mixed> $definition
     *
     * @return string|null The message for the person who typed the value, or null when it is fine.
     */
    public function validate(mixed $value, array $definition): ?string;

    /**
     * The StarDust type the field is indexed as when it is filterable, or null when this field cannot be filterable.
     *
     * @param array<string, mixed> $definition
     *
     * @return 'string'|'numeric'|'int'|'datetime'|null
     */
    public function kind(array $definition): ?string;

    /**
     * The key a list sorts by when the field is not indexed. A missing value sorts before every other.
     */
    public function sortKey(mixed $value): string|float|null;

    /**
     * The HTML a list shows for a value, already escaped. A missing value is a muted dash.
     *
     * @param array<string, mixed> $definition
     */
    public function cell(mixed $value, array $definition): string;

    /**
     * The HTML of the whole field in the entry form: its label, its input, its help and its error. The input posts as
     * `fields[<id>]`, or as `fields[<id>][]` when it holds a list.
     *
     * @param array<string, mixed> $definition
     * @param string|null          $error      The message to show under the input, or null.
     */
    public function input(mixed $value, array $definition, ?string $error = null): string;

    /**
     * The conditions a list can filter this field by, for a field that is filterable.
     *
     * Each one names an operator of the token API's filter (`eq`, `prefix`, `gte`, `lte`), a label for the control, the
     * kind of control to draw, and for a `select` the choices as value to label.
     *
     * @param array<string, mixed> $definition
     *
     * @return list<array{op: string, label: string, control: 'text'|'number'|'datetime-local'|'select', choices?: array<string, string>}>
     */
    public function filters(array $definition): array;
    /**
     * Read a field of the old format and return it as a version 2 definition of this type.
     *
     * @param array<string, mixed> $old     One entry of the old definition.
     * @param array<string, mixed> $context `modelIds` maps a model name to its id, for a data query that names one.
     *
     * @return array<string, mixed>
     *
     * @throws ConversionProblem When the field cannot be converted, with the reason.
     */
    public function fromLegacy(array $old, array $context = []): array;

    /**
     * Read a value stored in the old format and return it in the shape of this type.
     *
     * @param array<string, mixed> $definition The version 2 definition of the field.
     *
     * @throws ConversionProblem When the value fits no rule of the type. The caller keeps the old value and reports it.
     */
    public function valueFromLegacy(mixed $old, array $definition): mixed;
}
