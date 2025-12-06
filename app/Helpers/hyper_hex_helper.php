<?php
// hyper_hex_helper.php

/**
 * Hex Helper
 *
 * This helper provides two functions:
 *   - hex_encode(): Converts a given string into its hexadecimal representation.
 *   - hex_decode(): Reverses the process by converting a hexadecimal encoded string back to its original value.
 *
 * These utility functions can be useful when you need to represent binary data in a textual form.
 */

if (!function_exists('hex_encode')) {
    /**
     * Hex Encodes a given input string.
     *
     * @param string $input The string to be encoded.
     * @return string The hexadecimal representation of the input.
     *
     * Usage:
     * <code>
     *     $encoded = hex_encode("Hello World");
     *     echo $encoded; // 48656c6c6f20576f726c64
     * </code>
     */
    function hex_encode($input)
    {
        return bin2hex($input);
    }
}

if (!function_exists('hex_decode')) {
    /**
     * Decodes a hexadecimal encoded string.
     *
     * @param string $input The hexadecimal string to decode.
     * @return string The decoded original string.
     *
     * Usage:
     * <code>
     *     $decoded = hex_decode("48656c6c6f20576f726c64");
     *     echo $decoded; // Hello World
     * </code>
     */
    function hex_decode($input)
    {
        return pack("H*", $input);
    }
}
