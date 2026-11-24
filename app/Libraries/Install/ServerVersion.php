<?php

namespace App\Libraries\Install;

/**
 * Tells whether a database server is new enough for StarDust, which refuses MySQL older than 8.0.13 and MariaDB older than 10.11.
 * The check runs on the version text the server reports, such as `8.0.36` or `10.11.6-MariaDB-log`.
 */
final class ServerVersion
{
    public const MYSQL_FLOOR   = '8.0.13';
    public const MARIADB_FLOOR = '10.11.0';

    /**
     * @return array{flavor: string, number: string}
     */
    public static function parse(string $reported): array
    {
        $reported = trim($reported);
        $flavor   = stripos($reported, 'mariadb') !== false ? 'MariaDB' : 'MySQL';

        // MariaDB 10 used to report a 5.5.5- prefix in front of its own number over the replication protocol.
        $number = preg_match('/^(?:5\.5\.5-)?(\d+\.\d+\.\d+)/', $reported, $match) === 1 ? $match[1] : '0.0.0';

        return ['flavor' => $flavor, 'number' => $number];
    }

    public static function supported(string $reported): bool
    {
        $parsed = self::parse($reported);

        return version_compare($parsed['number'], $parsed['flavor'] === 'MariaDB' ? self::MARIADB_FLOOR : self::MYSQL_FLOOR, '>=');
    }

    /**
     * The name and number to show a person, such as `MariaDB 10.11.6`.
     */
    public static function label(string $reported): string
    {
        $parsed = self::parse($reported);

        return $parsed['flavor'] . ' ' . $parsed['number'];
    }
}
