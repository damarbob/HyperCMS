<?php

namespace App\Data\Entries;

use Transliterator;

/**
 * The search, find and sort rules a list applies to an entry's fields, now that the fields live in StarDust
 * and the database can no longer compare them.
 *
 * Each rule copies what the SQL did before, with the connection's `utf8mb4_general_ci` collation in mind, which
 * ignores case and accents. The comparison is built on `intl`, which can strip accents and change case, and
 * the sort compares code points of the folded text, as general_ci does. The known residues are the symbols and
 * the characters outside the Basic Multilingual Plane, which general_ci orders its own way.
 */
final class FieldRules
{
    /**
     * Whether a date such as `2026-01-05T09:30` counts as a date for the date sort. MySQL's
     * `STR_TO_DATE(value, '%Y-%m-%d %H:%i:%s')` returns NULL for it, because the format has a space where the
     * browser's value has a `T`, and the sort copies that. Set this to true to read the `T` as the separator.
     */
    public const ISO_T_AS_SEPARATOR = false;

    private const MAX_DECIMAL = 99999999.99;

    private static ?Transliterator $lower = null;
    private static ?Transliterator $upper = null;

    /**
     * Lowercase text with the accents taken off, the form the search compares.
     */
    public static function fold(string $text): string
    {
        if (! preg_match('/[^\x00-\x7F]/', $text)) {
            return strtolower($text);
        }

        self::$lower ??= Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower') ?: null;
        $folded = self::$lower?->transliterate($text);

        return is_string($folded) ? $folded : mb_strtolower($text);
    }

    /**
     * Uppercase text with the accents taken off and trailing spaces dropped, the form the sort compares with `strcmp()`.
     * The collation pads with spaces, so `a` and `a ` are the same value.
     */
    public static function sortFold(string $text): string
    {
        if (! preg_match('/[^\x00-\x7F]/', $text)) {
            return rtrim(strtoupper($text), ' ');
        }

        // The collation reads the sharp s as one s, where Unicode upper-casing makes it SS.
        $text = str_replace(['ß', 'ẞ'], 's', $text);

        self::$upper ??= Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Upper') ?: null;
        $folded = self::$upper?->transliterate($text);

        return rtrim(is_string($folded) ? $folded : mb_strtoupper($text), ' ');
    }

    /**
     * Whether the text holds the pattern anywhere, as `LIKE '%pattern%'` does. Both are folded first.
     * In the pattern `%` stands for any run of characters and `_` for any one character, and a backslash
     * in front of either makes it an ordinary character.
     */
    public static function contains(string $text, string $pattern): bool
    {
        $text    = self::fold($text);
        $pattern = self::fold($pattern);

        if ($pattern === '' || ! preg_match('/[%_]/', $pattern)) {
            return $pattern === '' || str_contains($text, $pattern);
        }

        $chars = preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return str_contains($text, $pattern);
        }

        $regex = '';
        for ($i = 0, $n = count($chars); $i < $n; $i++) {
            $char = $chars[$i];
            if ($char === '\\' && isset($chars[$i + 1]) && ($chars[$i + 1] === '%' || $chars[$i + 1] === '_')) {
                $regex .= preg_quote($chars[++$i], '/');
            } elseif ($char === '%') {
                $regex .= '.*';
            } elseif ($char === '_') {
                $regex .= '.';
            } else {
                $regex .= preg_quote($char, '/');
            }
        }

        return preg_match('/' . $regex . '/su', $text) === 1;
    }

    /**
     * The fields as the text lists show and search: JSON with unescaped unicode and slashes, and `{}` when empty.
     */
    public static function fieldsText(array $fields): string
    {
        if ($fields === []) {
            return '{}';
        }

        $json = json_encode((object) $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? '{}' : $json;
    }

    /**
     * A stored value as `JSON_UNQUOTE(JSON_EXTRACT(...))` returns it: a string as it is, anything else as MySQL's
     * JSON text, which puts a space after each comma and colon.
     */
    public static function jsonText(mixed $value): string
    {
        return is_string($value) ? $value : self::render($value);
    }

    private static function render(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return (string) json_encode($value);
        }
        if (is_string($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return '[' . implode(', ', array_map(self::render(...), $value)) . ']';
            }

            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = self::render((string) $key) . ': ' . self::render($item);
            }

            return '{' . implode(', ', $parts) . '}';
        }

        return self::render(json_decode((string) json_encode($value), true));
    }

    /**
     * The find rule of a model's list: the field's value holds the text, ignoring case and accents. A missing
     * field never matches, and `%` and `_` in the text are ordinary characters.
     */
    public static function findMatches(array $fields, string $field, string $text): bool
    {
        if (! array_key_exists($field, $fields)) {
            return false;
        }

        return str_contains(self::fold(self::jsonText($fields[$field])), self::fold($text));
    }

    /**
     * The sort key of a text field, or null when the entry has no such field.
     *
     * It is the value lowercased, trimmed and stripped of tags, and folded for comparison.
     */
    public static function textKey(array $fields, string $field): ?string
    {
        if (! array_key_exists($field, $fields)) {
            return null;
        }

        $text = preg_replace('~<[^>]+>~', '', self::jsonText($fields[$field])) ?? '';

        return self::sortFold(trim($text, ' '));
    }

    /**
     * The sort key of a number field, or null when the entry has no such field.
     *
     * It reads the number at the start of the text like `CAST(... AS DECIMAL(10,2))`, so a blank is 0,
     * the result has two decimals and a value too large is held at 99999999.99.
     */
    public static function numberKey(array $fields, string $field): ?float
    {
        if (! array_key_exists($field, $fields)) {
            return null;
        }

        $number = (float) self::jsonText($fields[$field]);
        if (is_nan($number)) {
            return 0.0;
        }

        return max(-self::MAX_DECIMAL, min(self::MAX_DECIMAL, round($number, 2)));
    }

    /**
     * The sort key of a date field as `Y-m-d H:i:s`, or null when the entry has no such field or the value is
     * not a date in the shape `STR_TO_DATE(value, '%Y-%m-%d %H:%i:%s')` reads.
     */
    public static function dateKey(array $fields, string $field): ?string
    {
        if (! array_key_exists($field, $fields)) {
            return null;
        }

        return self::parseDate(self::jsonText($fields[$field]));
    }

    /**
     * Read a date the way `STR_TO_DATE(text, '%Y-%m-%d %H:%i:%s')` does. Spaces before a number are skipped,
     * the numbers may be short, a missing time is midnight, and text after the seconds is ignored.
     */
    public static function parseDate(string $text): ?string
    {
        // Each step is a number of at most that many digits, or the character the format puts between them.
        $steps = [4, '-', 2, '-', 2, 2, ':', 2, ':', 2];
        $names = [0 => 'year', 2 => 'month', 4 => 'day', 5 => 'hour', 7 => 'minute', 9 => 'second'];
        $value = ['year' => 0, 'month' => 0, 'day' => 0, 'hour' => 0, 'minute' => 0, 'second' => 0];
        $length = strlen($text);
        $at     = 0;
        $yearDigits = 0;

        foreach ($steps as $index => $step) {
            if (is_int($step)) {
                $at += strspn($text, " \t\n\r\v\f", $at);
                if (self::ISO_T_AS_SEPARATOR && $index === 5 && ($text[$at] ?? '') === 'T') {
                    $at++;
                }
            }
            if ($at >= $length) {
                break;
            }

            if (is_string($step)) {
                if ($text[$at] !== $step) {
                    return null;
                }
                $at++;

                continue;
            }

            $digits = strspn($text, '0123456789', $at, $step);
            if ($digits === 0) {
                return null;
            }
            $value[$names[$index]] = (int) substr($text, $at, $digits);
            $at += $digits;
            if ($index === 0) {
                $yearDigits = $digits;
            }
        }

        if ($yearDigits <= 2 && $yearDigits > 0) {
            $value['year'] += $value['year'] < 70 ? 2000 : 1900;
        }

        // MySQL's default modes refuse a zero year, month or day and an hour, minute or second out of range.
        if ($value['year'] < 1 || $value['month'] < 1 || $value['month'] > 12 || $value['day'] < 1 || $value['day'] > 31
            || $value['hour'] > 23 || $value['minute'] > 59 || $value['second'] > 59) {
            return null;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $value['year'], $value['month'], $value['day'], $value['hour'], $value['minute'], $value['second']);
    }

    /**
     * The sort key of one entry for a field of the given kind: `text`, `number` or `date`.
     */
    public static function sortKey(string $kind, array $fields, string $field): string|float|null
    {
        return match ($kind) {
            'number' => self::numberKey($fields, $field),
            'date'   => self::dateKey($fields, $field),
            default  => self::textKey($fields, $field),
        };
    }

    /**
     * Compare two sort keys. A missing key comes before every other, as NULL does in MySQL.
     */
    public static function compare(string|float|null $a, string|float|null $b): int
    {
        if ($a === null || $b === null) {
            return ($a === null ? 0 : 1) <=> ($b === null ? 0 : 1);
        }

        return is_string($a) && is_string($b) ? strcmp($a, $b) : $a <=> $b;
    }
}
