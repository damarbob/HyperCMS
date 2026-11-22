<?php

namespace App\Cells;

/**
 * Small helpers the cells share to write HTML safely.
 */
final class Html
{
    /**
     * Escape a value for an attribute written between double quotes.
     */
    public static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Turn an array into attributes with escaped values. A key that is not a plain attribute name is skipped, so a
     * caller cannot slip in markup through a key. `true` writes a bare attribute and `false` or null writes none.
     *
     * @param array<string, mixed> $attributes
     */
    public static function attrs(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            if (! is_string($name) || preg_match('/^[a-zA-Z_:@][-a-zA-Z0-9_:.@]*$/', $name) !== 1 || $value === false || $value === null) {
                continue;
            }
            $out .= $value === true ? " $name" : " $name=\"" . Html::attr((string) $value) . '"';
        }

        return $out;
    }

    /**
     * Join class names, leaving out the empty ones.
     *
     * @param list<string|null|false> $classes
     */
    public static function classes(array $classes): string
    {
        return Html::attr(implode(' ', array_filter($classes, static fn ($c): bool => is_string($c) && $c !== '')));
    }
}
