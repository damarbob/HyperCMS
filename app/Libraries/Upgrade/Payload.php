<?php

namespace App\Libraries\Upgrade;

use stdClass;

/**
 * Reads the text an entry version was saved as and says what it holds.
 *
 * HyperCMS 0.1 saved fields as a list, `[{"id": "title", "value": "x"}]`. Later versions save an
 * object, `{"title": "x"}`. StarDust 0.3 stores only an object, so the list is converted and a
 * blank value becomes `{}`.
 */
final class Payload
{
    /**
     * Turn `[{"id": "title", "value": "x"}]` into `{"title": "x"}`.
     *
     * @param bool|null $twice Set to true when the list names one id more than once.
     *
     * @return stdClass|false|null The new fields, false for a list in some other shape, or null when
     *                             there is nothing to convert (already converted, empty, or not JSON).
     */
    public static function keyValue(?string $raw, ?bool &$twice = null): stdClass|false|null
    {
        $twice = false;
        $data  = json_decode((string) $raw);
        if (! is_array($data) || $data === []) {
            return null;
        }

        $fields = new stdClass();
        foreach ($data as $item) {
            if (! $item instanceof stdClass || ! property_exists($item, 'value') || ! is_scalar($item->id ?? null)) {
                return false;
            }
            $twice = $twice || property_exists($fields, (string) $item->id);
            $fields->{(string) $item->id} = $item->value;
        }

        return $fields;
    }

    /**
     * Write fields as JSON text. Text is kept unescaped, as the admin saves it, because search matches the stored text.
     *
     * Characters outside the Basic Multilingual Plane, such as emoji, are written as JSON escape pairs instead.
     * A connection or column on three byte `utf8` cannot take them, and MySQL then replaces each one with a
     * question mark without raising an error.
     */
    public static function encode(stdClass $fields): string
    {
        $json = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        return preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', static function (array $match): string {
            $offset = mb_ord($match[0], 'UTF-8') - 0x10000;

            return sprintf('\u%04x\u%04x', 0xD800 + ($offset >> 10), 0xDC00 + ($offset & 0x3FF));
        }, $json);
    }

    /**
     * Convert the stored text of one version to the object format.
     *
     * A list in some other shape comes back unchanged, and `$odd` says so.
     *
     * @param bool|null $odd   Set to true when the text is a list this class cannot convert.
     * @param bool|null $twice Set to true when the list names one id more than once.
     */
    public static function convert(?string $raw, ?bool &$odd = null, ?bool &$twice = null): ?string
    {
        $fields = self::keyValue($raw, $twice);
        $odd    = $fields === false;

        return $fields instanceof stdClass ? self::encode($fields) : $raw;
    }

    /**
     * Whether stored text holds no field values at all: nothing, an empty string, `[]`, `{}`, `""` or `[{}]`.
     */
    public static function isBlank(?string $raw): bool
    {
        if ($raw === null || trim($raw) === '') {
            return true;
        }

        $data = json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return match (true) {
            $data === null, $data === '', $data === [] => true,
            $data instanceof stdClass => (array) $data === [],
            is_array($data) && count($data) === 1 && $data[0] instanceof stdClass => (array) $data[0] === [],
            default => false,
        };
    }

    /**
     * Turn the stored text of a current version into the fields to give StarDust.
     *
     * @return array|null The fields, with `[]` for a blank value, or null when the text is not an object
     *                    and cannot be made into one.
     */
    public static function fieldsFor(?string $raw): ?array
    {
        if (self::isBlank($raw)) {
            return [];
        }

        $data = json_decode((string) $raw, true);

        return is_array($data) && ! array_is_list($data) ? $data : null;
    }

    /**
     * Whether two stored values are the same once decoded, because MySQL rewrites the text of a JSON column.
     */
    public static function same(?string $a, ?string $b): bool
    {
        return self::fieldsFor($a) === self::fieldsFor($b) || json_decode((string) $a, true) == json_decode((string) $b, true);
    }
}
