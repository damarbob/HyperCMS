<?php

if (!function_exists('replace_placeholders')) {
    /**
     * Replaces placeholders in a string with values from an array.
     * Placeholders are defined by curly braces, e.g., {x}, {y}, {z}.
     *
     * @param string $str The string containing placeholders.
     * @param array  $replacements An associative array mapping keys to replacement values.
     * @return string The string with all placeholders replaced.
     */
    function replace_placeholders(string $str, array $replacements): string
    {
        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($replacements) {
            $key = $matches[1];
            return array_key_exists($key, $replacements) ? $replacements[$key] : $matches[0];
        }, $str);
    }
}
