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
