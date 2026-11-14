<?php
declare(strict_types=1);

// Database access for the regression scripts. The settings come from the repo's .env, so the
// scripts always work on the database the app itself would open.

const MARKER_TABLE = 'regression_marker';

/**
 * @return array{hostname: string, port: int, database: string, username: string, password: string}
 */
function dbSettings(string $envFile): array
{
    $found = [];
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        // The first uncommented line of a key wins, as it does in the app.
        if (preg_match('/^\s*database[._]default[._](hostname|port|database|username|password)\s*=\s*(.*)$/', $line, $m) === 1 && ! isset($found[$m[1]])) {
            $found[$m[1]] = envValue($m[2]);
        }
    }
    if (($found['database'] ?? '') === '') {
        fwrite(STDERR, "No database_default_database line found in $envFile\n");
        exit(2);
    }
    $host = $found['hostname'] ?? 'localhost';

    return [
        // "localhost" makes the MySQL client look for a socket file on Linux and macOS.
        'hostname' => $host === 'localhost' ? '127.0.0.1' : $host,
        'port'     => (int) ($found['port'] ?? 3306) ?: 3306,
        'database' => $found['database'],
        'username' => $found['username'] ?? 'root',
        'password' => $found['password'] ?? '',
    ];
}

function envValue(string $raw): string
{
    $raw = trim($raw);
    if ($raw !== '' && ($raw[0] === '"' || $raw[0] === "'")) {
        $end = strpos($raw, $raw[0], 1);

        return $end === false ? substr($raw, 1) : substr($raw, 1, $end - 1);
    }

    return trim((string) preg_replace('/\s+#.*$/', '', $raw));
}

/**
 * @param array{hostname: string, port: int, database: string, username: string, password: string} $s
 */
function connect(array $s, bool $useDatabase): PDO
{
    $dsn = "mysql:host={$s['hostname']};port={$s['port']};charset=utf8mb4" . ($useDatabase ? ";dbname={$s['database']}" : '');
    try {
        return new PDO($dsn, $s['username'], $s['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        fwrite(STDERR, "Cannot reach the database server at {$s['hostname']}:{$s['port']}. " . $e->getMessage() . "\n");
        exit(1);
    }
}
