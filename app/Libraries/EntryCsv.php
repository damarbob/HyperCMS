<?php

namespace App\Libraries;

use App\Data\Files;

/**
 * Turns the values of an entry into the text of a CSV cell.
 *
 * A cell that starts with `=`, `+`, `-` or `@` would be run as a formula by a spreadsheet, so it gets a quote in front of it.
 */
final class EntryCsv
{
    /**
     * The text of one value, for the field it belongs to.
     *
     * @param array<string, mixed> $definition
     */
    public static function value(mixed $value, array $definition): string
    {
        $text = match (true) {
            $value === null                       => '',
            is_bool($value)                       => lang($value ? 'Fields.yes' : 'Fields.no'),
            ($definition['type'] ?? '') === 'file'   => implode(', ', array_map(static fn ($item): string => self::fileLink($item), (array) $value)),
            ($definition['type'] ?? '') === 'choice' => implode(', ', array_map(static fn ($item): string => self::choiceLabel($item, $definition), (array) $value)),
            is_array($value)                      => implode(', ', array_map(static fn ($item): string => is_scalar($item) ? (string) $item : (string) json_encode($item, JSON_UNESCAPED_UNICODE), $value)),
            is_scalar($value)                     => (string) $value,
            default                               => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };

        // Strip markup from a rich text, so the sheet holds the words.
        if (($definition['type'] ?? '') === 'richtext') {
            $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return self::safe($text);
    }

    /**
     * Protect a cell from being read as a formula.
     */
    public static function safe(string $text): string
    {
        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $text : $text;
    }

    /**
     * Write rows as CSV text, with a byte order mark so a spreadsheet reads the UTF-8.
     *
     * @param list<string>       $header
     * @param list<list<string>> $rows
     */
    public static function write(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }
        rewind($handle);
        $text = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $text;
    }

    /**
     * The address of a library file, so the sheet holds a link that opens, and the id of a file that is not there.
     */
    private static function fileLink(mixed $item): string
    {
        $file = is_int($item) ? service('files')->find($item) : null;

        return $file !== null ? Files::url($file) : (is_scalar($item) ? (string) $item : '');
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function choiceLabel(mixed $item, array $definition): string
    {
        foreach (is_array($definition['choices'] ?? null) ? $definition['choices'] : [] as $choice) {
            if (is_array($choice) && (string) ($choice['value'] ?? '') === (string) $item) {
                return (string) ($choice['label'] ?? $item);
            }
        }

        return is_scalar($item) ? (string) $item : '';
    }
}
