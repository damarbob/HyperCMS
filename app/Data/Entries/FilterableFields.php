<?php

namespace App\Data\Entries;

use App\Fields\FieldTypes;
use App\Libraries\Upgrade\Preflight;
use InvalidArgumentException;

/**
 * Which fields of a model are filterable, which StarDust type each one is indexed as, and the clean-up a value
 * gets on its way in.
 *
 * A field is filterable when its definition says `"filterable": true`. StarDust then indexes it, so a list can be
 * sorted on it and the token API can filter on it. The flag is off by default and a field without it is stored and
 * read exactly as before. StarDust coerces the value of an indexed field and refuses what it cannot read, so a value
 * is cleaned here before anything is written, and one that cannot be cleaned is refused with a message for the admin.
 *
 * The StarDust type of a field comes from its field type (`App\Fields\FieldType::kind()`):
 *
 * | Field type                                   | StarDust type |
 * | :--                                          | :--           |
 * | `text`, `color`, `hook`, a `choice` of one   | `string`      |
 * | `number`                                     | `numeric`, or `int` when the field is whole numbers only |
 * | `relation` of one, `model`                   | `int`         |
 * | `datetime`                                   | `datetime`    |
 *
 * `longtext`, `richtext`, `code`, `boolean`, `file`, `hidden`, and a `choice` or `relation` of several cannot be
 * filterable. Their values are long, lists or not values at all.
 */
final class FilterableFields
{
    /**
     * The most characters a filterable string may hold, which is StarDust's limit for an indexed text.
     */
    public const MAX_STRING = 4096;

    /**
     * The number text StarDust's retype backfill reads, which is the JSON number grammar.
     */
    private const NUMBER = '/^-?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][+-]?[0-9]+)?$/';

    private static ?FieldTypes $registry = null;

    private static function registry(): FieldTypes
    {
        return self::$registry ??= FieldTypes::core();
    }

    /**
     * The types a field can be filterable as, for a message.
     *
     * @return list<string>
     */
    public static function types(): array
    {
        $names = [];
        foreach (self::registry()->names() as $name) {
            if (self::kind(['type' => $name, 'multiple' => false]) !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The StarDust type a field of this definition would be indexed as, or null when its type cannot be filterable.
     *
     * @param array<string, mixed> $field One entry of the model definition.
     *
     * @return 'string'|'numeric'|'int'|'datetime'|null
     */
    public static function kind(array $field): ?string
    {
        $type = $field['type'] ?? null;

        return is_string($type) ? self::registry()->get($type)?->kind($field) : null;
    }

    /**
     * Whether the definition asks for this field to be filterable.
     *
     * @param array<string, mixed> $field
     */
    public static function flagged(array $field): bool
    {
        return isset($field['filterable']) && filter_var($field['filterable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }

    /**
     * Read the filterable fields of a model definition.
     *
     * A field id that repeats counts once, by its first entry, as it does when the model is registered.
     *
     * @return array{fields: array<string, string>, refused: array<string, string>} `fields` holds each valid field
     *         with the StarDust type it is indexed as, and `refused` holds the field ids that ask for the flag on a type
     *         that cannot take it, each with its type.
     */
    public static function read(?string $definition): array
    {
        $fields  = [];
        $refused = [];
        $seen    = [];

        foreach (Preflight::definitionFields($definition) as $field) {
            $id = $field['id'] ?? null;
            if (! is_scalar($id) || (string) $id === '' || isset($seen[(string) $id])) {
                continue;
            }
            $seen[(string) $id] = true;

            if (! self::flagged($field)) {
                continue;
            }

            $kind = self::kind($field);
            if ($kind === null) {
                $refused[(string) $id] = is_scalar($field['type'] ?? null) ? (string) $field['type'] : '';
            } else {
                $fields[(string) $id] = $kind;
            }
        }

        return ['fields' => $fields, 'refused' => $refused];
    }

    /**
     * Refuse a definition that marks a field of a type that cannot be filterable.
     *
     * @throws InvalidArgumentException With a message for the admin, naming the first field.
     */
    public static function assertValid(?string $definition): void
    {
        $refused = self::read($definition)['refused'];
        foreach ($refused as $id => $type) {
            throw new InvalidArgumentException(lang('Filterable.unsupportedType', [
                'field' => $id,
                'type'  => $type === '' ? '-' : $type,
                'types' => implode(', ', self::types()),
            ]));
        }
    }

    /**
     * Clean the values of the filterable fields of one entry, and leave every other field as it is.
     *
     * @param array<string, mixed>  $fields The entry's fields.
     * @param array<string, string> $kinds  The filterable fields of its model, from `read()`.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException When a value cannot be read as its field's type, with a message for the admin.
     */
    public static function normalise(array $fields, array $kinds): array
    {
        foreach ($kinds as $id => $kind) {
            if (array_key_exists($id, $fields)) {
                $fields[$id] = self::value((string) $id, $kind, $fields[$id]);
            }
        }

        return $fields;
    }

    /**
     * Clean one value for its StarDust type.
     *
     * A blank or a zero date in a number or date field becomes null and never 0, a date's `T` becomes a space and its
     * missing seconds become `:00`, and a number is trimmed. A text is kept as it is.
     *
     * @throws InvalidArgumentException When the value cannot be read as the type.
     */
    public static function value(string $field, string $kind, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($kind) {
            'numeric'  => self::numeric($field, $value),
            'int'      => self::integer($field, $value),
            'datetime' => self::datetime($field, $value),
            default    => self::string($field, $value),
        };
    }

    private static function string(string $field, mixed $value): mixed
    {
        if (! is_scalar($value)) {
            throw new InvalidArgumentException(lang('Filterable.notSingleValue', ['field' => $field]));
        }
        if (mb_strlen((string) $value) > self::MAX_STRING) {
            throw new InvalidArgumentException(lang('Filterable.tooLong', ['field' => $field, 'max' => self::MAX_STRING]));
        }

        return $value;
    }

    private static function numeric(string $field, mixed $value): int|float|string|null
    {
        if (is_int($value) || (is_float($value) && is_finite($value))) {
            return $value;
        }
        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                return null;
            }
            if (preg_match(self::NUMBER, $text) === 1) {
                return $text;
            }
            // `.5`, `+5` and `007` are numbers to StarDust's write but not to its backfill, so they are written out once.
            if (is_numeric($text) && is_finite((float) $text)) {
                return self::canonical((float) $text);
            }
        }

        throw new InvalidArgumentException(lang('Filterable.notNumber', ['field' => $field, 'value' => self::shown($value)]));
    }

    private static function integer(string $field, mixed $value): int|string|null
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 9.2e18) {
            return (int) $value;
        }
        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                return null;
            }
            if (preg_match('/^-?[0-9]+$/', $text) === 1) {
                $digits = ltrim(ltrim($text, '-'), '0');
                $number = $digits === '' ? 0 : (int) $text;
                if ($digits === '' || (string) abs($number) === $digits) {
                    return $number;
                }
            }
        }

        throw new InvalidArgumentException(lang('Filterable.notInteger', ['field' => $field, 'value' => self::shown($value)]));
    }

    private static function datetime(string $field, mixed $value): ?string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(lang('Filterable.notDate', ['field' => $field, 'value' => self::shown($value)]));
        }

        $text = trim($value);
        // The browser's empty value and the zero date an old form could send mean no date.
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2})(\.\d{1,6})?)?$/', $text, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[4] <= 23 && (int) $m[5] <= 59 && (int) ($m[6] ?? 0) <= 59) {
            return sprintf('%s-%s-%s %s:%s:%s%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6] ?? '00', $m[7] ?? '');
        }

        throw new InvalidArgumentException(lang('Filterable.notDate', ['field' => $field, 'value' => self::shown($value)]));
    }

    private static function canonical(float $number): string
    {
        $text = (string) json_encode($number);

        return preg_match(self::NUMBER, $text) === 1 ? $text : sprintf('%.15g', $number);
    }

    /**
     * A value as a message shows it, cut short.
     */
    private static function shown(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : get_debug_type($value);

        return mb_strlen($text) > 40 ? mb_substr($text, 0, 40) . '...' : $text;
    }
}
