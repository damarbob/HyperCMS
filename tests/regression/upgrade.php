<?php
declare(strict_types=1);

// Test `php spark hyper:upgrade`. Run it on a freshly restored database.
//   php upgrade.php <envFile> <repoDir>
//
// The seed is in today's layout, so the script first puts it back into the shape HyperCMS 0.1 left
// behind, then upgrades it and checks that every row came back as it was in the seed.
require __DIR__ . '/lib.php';

if (count($argv) < 3) {
    fwrite(STDERR, "usage: upgrade.php <envFile> <repoDir>\n");
    exit(2);
}
[, $envFile, $repo] = $argv;
$pdo = connect(dbSettings($envFile), true);

const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

$failed = 0;
$check  = function (string $what, bool $ok, string $detail = '') use (&$failed): void {
    echo ($ok ? 'ok    ' : 'FAIL  ') . $what . ($ok || $detail === '' ? '' : "  ($detail)") . PHP_EOL;
    $failed += $ok ? 0 : 1;
};
$pairs = fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
$rows  = fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/** The fields of one entry version as a map, whichever of the two formats it is stored in. */
$asMap = function (string $json): mixed {
    $data = json_decode($json);
    if (! is_array($data) || $data === []) {
        return $data;
    }
    $map = new stdClass();
    foreach ($data as $item) {
        if (! $item instanceof stdClass) {
            return $data;
        }
        $map->{(string) $item->id} = $item->value;
    }

    return $map;
};
$versions = function () use ($rows, $asMap): array {
    $out = [];
    foreach ($rows('SELECT id, fields, updated_at, deleted_at FROM entry_data ORDER BY id') as $row) {
        $out[$row['id']] = ['fields' => json_encode($asMap($row['fields']), FLAGS), 'updated_at' => $row['updated_at'], 'deleted_at' => $row['deleted_at'], 'raw' => $row['fields']];
    }

    return $out;
};
$spark = function (string $arguments) use ($repo): array {
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $here = getcwd();
    chdir($repo);
    exec("php spark hyper:upgrade $arguments < $null 2>&1", $output, $code);
    chdir((string) $here);

    return [$code, implode("\n", $output)];
};

$indexes = fn(string $table): array => array_values(array_unique(array_column($rows("SHOW INDEX FROM `$table`"), 'Key_name')));

// What the upgrade has to arrive at.
$wantIndexes  = array_merge(...array_map($indexes, ['entries', 'entry_data', 'models', 'model_data']));
$wantEntries  = $pairs('SELECT id, current_entry_data_id FROM entries ORDER BY id');
$wantModels   = $pairs('SELECT id, current_model_data_id FROM models ORDER BY id');
$wantVersions = $versions();

// Put the database into the 0.1 shape.
$update = $pdo->prepare('UPDATE entry_data SET fields = ? WHERE id = ?');
foreach ($rows('SELECT id, fields FROM entry_data') as $row) {
    $data = json_decode($row['fields']);
    if ($data instanceof stdClass) {
        $list = [];
        foreach (get_object_vars($data) as $id => $value) {
            $list[] = ['id' => (string) $id, 'value' => $value];
        }
        $update->execute([json_encode($list, FLAGS), $row['id']]);
    }
}
// One version in a shape that is neither format. The upgrade must leave it alone and say so.
$pdo->exec("INSERT INTO entry_data (entry_id, fields, creator_id, created_at, updated_at, deleted_at) SELECT 1, '[\"loose\", \"list\"]', creator_id, created_at, updated_at, '2026-01-01 00:00:00' FROM entry_data WHERE id = 1");
$oddId = (int) $pdo->lastInsertId();

$pdo->exec('ALTER TABLE entries DROP COLUMN current_entry_data_id, DROP INDEX idx_entries_model_history, DROP INDEX idx_entries_deleted_at');
$pdo->exec('ALTER TABLE models DROP COLUMN current_model_data_id');
$pdo->exec('ALTER TABLE entry_data DROP INDEX idx_entry_data_history');
$pdo->exec('ALTER TABLE model_data DROP INDEX idx_model_data_history');
$pdo->exec("DELETE FROM migrations WHERE namespace = 'StarDust' AND class NOT LIKE '%CreateHyperTables'");
$pdo->exec("UPDATE migrations SET class = 'App\\\\Database\\\\Migrations\\\\CreateHyperTables', namespace = 'App' WHERE class LIKE '%CreateHyperTables'");

$legacy = $versions();

// A dry run and an unanswered backup question change nothing.
[$code, $text] = $spark('--dry-run');
$check('dry run exits 0', $code === 0, $text);
$check('dry run counts the old versions', str_contains($text, 'old field format: ' . count($legacy)), $text);
[$code, $text] = $spark('');
$check('no answer to the backup question exits 1', $code === 1, $text);
$check('nothing changed before the real run', $versions() === $legacy && $pairs("SELECT namespace, COUNT(*) FROM migrations WHERE class LIKE '%CreateHyperTables' GROUP BY namespace") === ['App' => 1]);

// The real run. The odd version makes it end with an error after doing everything else.
[$code, $text] = $spark('--yes');
echo preg_replace('/^/m', '      | ', $text) . PHP_EOL;
$check('upgrade reports the odd version and exits 1', $code === 1 && str_contains($text, "entry_data ids: $oddId"), "exit $code");

$history = $pairs("SELECT class, namespace FROM migrations WHERE namespace IN ('App', 'StarDust') ORDER BY class");
$check('migration history is under StarDust', $history === [
    'StarDust\Database\Migrations\AddCurrentVersionColumns' => 'StarDust',
    'StarDust\Database\Migrations\AddPerformanceIndexes'    => 'StarDust',
    'StarDust\Database\Migrations\CreateAuthTablesPolyfill' => 'StarDust',
    'StarDust\Database\Migrations\CreateHyperTables'        => 'StarDust',
], json_encode($history));

$lost = array_diff($wantIndexes, array_merge(...array_map($indexes, ['entries', 'entry_data', 'models', 'model_data'])));
$check('every index the seed had is back', $lost === [], implode(', ', $lost));

$check('every entry points at the version the seed had', $pairs('SELECT id, current_entry_data_id FROM entries ORDER BY id') === $wantEntries);
$check('every model points at the version the seed had', $pairs('SELECT id, current_model_data_id FROM models ORDER BY id') === $wantModels);

$now = $versions();
$odd = $now[$oddId] ?? null;
unset($now[$oddId]);
$sameFields = $sameDates = $newFormat = true;
foreach ($wantVersions as $id => $want) {
    $sameFields = $sameFields && ($now[$id]['fields'] ?? null) === $want['fields'];
    $sameDates  = $sameDates && ($now[$id]['updated_at'] ?? null) === $want['updated_at'] && ($now[$id]['deleted_at'] ?? null) === $want['deleted_at'];
    $newFormat  = $newFormat && (! is_array(json_decode($now[$id]['raw'] ?? '[1]')) || $now[$id]['raw'] === '[]');
}
$check('every version holds the values it had, trashed ones included', $sameFields && count($now) === count($wantVersions));
$check('every version is in the new format', $newFormat);
$check('no modified or trashed date moved', $sameDates);
$check('the odd version is untouched', $odd !== null && $odd['raw'] === '["loose", "list"]');

// With the odd version gone, a second run finds nothing to do and succeeds.
$pdo->exec("DELETE FROM entry_data WHERE id = $oddId");
$state = fn(): array => [$versions(), $pairs('SELECT id, current_entry_data_id FROM entries ORDER BY id'), $pairs('SELECT id, current_model_data_id FROM models ORDER BY id'), $rows('SELECT class, namespace FROM migrations ORDER BY id')];
$settled = $state();
[$code, $text] = $spark('--yes');
$check('a second run exits 0', $code === 0, $text);
$check('a second run changes nothing', $state() === $settled);

echo ($failed === 0 ? 'upgrade test passed' : "upgrade test: $failed checks failed") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
