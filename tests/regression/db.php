<?php
declare(strict_types=1);

// Restore or drop the regression database, which is the one named in the repo's .env.
//   php db.php <envFile> restore <dumpFile>
//   php db.php <envFile> drop
// Both refuse to touch a database that holds tables and was not created by this script.
require __DIR__ . '/lib.php';

$envFile = $argv[1] ?? '';
$action  = $argv[2] ?? '';
if (! is_file($envFile) || ! in_array($action, ['restore', 'drop'], true)) {
    fwrite(STDERR, "usage: db.php <envFile> restore <dumpFile> | drop\n");
    exit(2);
}

$settings = dbSettings($envFile);
$name     = $settings['database'];
$pdo      = connect($settings, false);

$quoted = '`' . str_replace('`', '``', $name) . '`';
$tables = [];
if ($pdo->query('SHOW DATABASES LIKE ' . $pdo->quote(addcslashes($name, '_%\\')))->fetchColumn() !== false) {
    $tables = $pdo->query("SHOW TABLES FROM $quoted")->fetchAll(PDO::FETCH_COLUMN);
}
if ($tables !== [] && ! in_array(MARKER_TABLE, $tables, true)) {
    fwrite(STDERR, "Database '$name' already holds tables and has no " . MARKER_TABLE . " table, so these scripts did not create it. Nothing was changed. Point .env at a database name that is free to drop.\n");
    exit(1);
}

$pdo->exec("DROP DATABASE IF EXISTS $quoted");
if ($action === 'drop') {
    echo "dropped $name\n";
    exit(0);
}

$dump = $argv[3] ?? '';
if (! is_file($dump)) {
    fwrite(STDERR, "Dump file not found: $dump\n");
    exit(2);
}
$pdo->exec("CREATE DATABASE $quoted CHARACTER SET utf8mb4");
$pdo->exec("USE $quoted");
$pdo->exec('CREATE TABLE ' . MARKER_TABLE . ' (created_at DATETIME NOT NULL)');
$pdo->exec('INSERT INTO ' . MARKER_TABLE . ' VALUES (NOW())');

// The dump is many statements. Walking the result sets is what surfaces an error in a later one.
try {
    $statement = $pdo->query((string) file_get_contents($dump));
    while ($statement->nextRowset()) {
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'The dump stopped part way. ' . $e->getMessage() . "\n");
    exit(1);
}
echo "restored $name from " . basename($dump) . "\n";
