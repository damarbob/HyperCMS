<?php

namespace App\Libraries;

/**
 * The secret that lets a scheduled URL fetch run the background work. With no secret set, or one too short to resist guessing,
 * the address does not exist.
 */
final class TickKey
{
    public const MIN_LENGTH = 32;

    public static function enabled(string $secret): bool
    {
        return strlen($secret) >= self::MIN_LENGTH;
    }

    public static function matches(string $secret, ?string $given): bool
    {
        return self::enabled($secret) && $given !== null && $given !== '' && hash_equals($secret, $given);
    }
}
