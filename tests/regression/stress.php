<?php
declare(strict_types=1);

// Test `php spark hyper:upgrade` on a large database full of awkward data. Run it on a freshly restored database.
//   php stress.php <envFile> <repoDir> [--entries=12000] [--memory=128M]
//
// Nobody has handed over a real 0.1 database, so this builds one from assumptions about what such a database
// could hold: many entries with several versions each, big payloads, emoji, odd field shapes and inconsistent
// trash flags. It then upgrades it and compares every row with what the generator put in.
require __DIR__ . '/lib.php';

if (count($argv) < 3) {
    fwrite(STDERR, "usage: stress.php <envFile> <repoDir> [--entries=N] [--memory=128M]\n");
    exit(2);
}
[, $envFile, $repo] = $argv;
$entryCount = 12000;
$memory     = '128M';
foreach (array_slice($argv, 3) as $arg) {
    if (preg_match('/^--entries=(\d+)$/', $arg, $m) === 1) {
        $entryCount = (int) $m[1];
    } elseif (preg_match('/^--memory=(\S+)$/', $arg, $m) === 1) {
        $memory = $m[1];
    }
}
$pdo = connect(dbSettings($envFile), true);

const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

$failed = 0;
$check  = function (string $what, bool $ok, string $detail = '') use (&$failed): void {
    echo ($ok ? 'ok    ' : 'FAIL  ') . $what . ($ok || $detail === '' ? '' : "  ($detail)") . PHP_EOL;
    $failed += $ok ? 0 : 1;
};
$rows = fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------- build a database that looks like 0.1
mt_srand(2026);

$pdo->exec('ALTER TABLE entries DROP COLUMN current_entry_data_id, DROP INDEX idx_entries_model_history, DROP INDEX idx_entries_deleted_at');
$pdo->exec('ALTER TABLE models DROP COLUMN current_model_data_id');
$pdo->exec('ALTER TABLE entry_data DROP INDEX idx_entry_data_history');
$pdo->exec('ALTER TABLE model_data DROP INDEX idx_model_data_history');
$pdo->exec("DELETE FROM migrations WHERE namespace = 'StarDust' AND class NOT LIKE '%CreateHyperTables'");
$pdo->exec("UPDATE migrations SET class = 'App\\\\Database\\\\Migrations\\\\CreateHyperTables', namespace = 'App' WHERE class LIKE '%CreateHyperTables'");
$pdo->exec('DELETE FROM entry_data');
$pdo->exec('DELETE FROM entries');
$pdo->exec('DELETE FROM model_data');
$pdo->exec('DELETE FROM models');
foreach (['entry_data', 'entries', 'model_data', 'models'] as $table) {
    $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
}

$words = ['Selamat', 'datang', 'di', 'toko', 'kami', 'harga', 'promo', 'Éclair', 'Ångström', 'naïve', 'café', 'jalan', 'Kebon', 'Jeruk', 'berita', 'produk', 'artikel', 'laporan', 'tahunan', 'rapat'];
$sentence = function (int $n) use ($words): string {
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $out[] = $words[mt_rand(0, count($words) - 1)];
    }

    return implode(' ', $out);
};
$date = fn(int $from, int $to): string => date('Y-m-d H:i:s', mt_rand($from, $to));
$t0   = strtotime('2023-01-01');
$t1   = strtotime('2026-10-01');

// One ordinary field list. The value kinds are the ones the admin forms produce.
$ordinary = function (int $i) use ($sentence): array {
    return [
        ['id' => 'title', 'value' => $sentence(mt_rand(2, 7))],
        ['id' => 'body', 'value' => '<p>' . $sentence(mt_rand(10, 120)) . '</p><p style="x">"quoted" back\\slash ' . $sentence(5) . '</p>'],
        ['id' => 'views', 'value' => (string) mt_rand(0, 100000)],
        ['id' => 'price', 'value' => mt_rand(0, 1) === 0 ? '' : number_format(mt_rand(100, 9999999) / 100, 2, '.', '')],
        ['id' => 'published_at', 'value' => ['', '2025-03-20T10:30', '2025-03-20T10:30:15', '0000-00-00T00:00'][mt_rand(0, 3)]],
        ['id' => 'tags', 'value' => array_slice(['news', 'tech', 'promo', 'event'], 0, mt_rand(0, 4))],
        ['id' => 'flag', 'value' => [true, false, null, 0, 1.5, '1e3'][mt_rand(0, 5)]],
    ];
};
// The awkward lists, by name. A name makes a failure readable.
$awkward = [
    'emoji written escaped'     => [['id' => 'title', 'value' => "Grand opening \u{1F389} at the caf\u{E9}"]],
    'emoji written raw'         => [['id' => 'title', 'value' => "Raw \u{1F600} emoji and \u{1F1EE}\u{1F1E9}"]],
    'cjk and rtl'               => [['id' => 'title', 'value' => '日本語のタイトル'], ['id' => 'note', 'value' => 'مرحبا بالعالم']],
    'duplicate ids'             => [['id' => 'title', 'value' => 'first'], ['id' => 'title', 'value' => 'second']],
    'numeric ids'               => [['id' => 7, 'value' => 'seven'], ['id' => 8, 'value' => 'eight']],
    'empty id'                  => [['id' => '', 'value' => 'blank id']],
    'nested object value'       => [['id' => 'meta', 'value' => ['a' => ['b' => [1, 2, ['c' => null]]]]]],
    'huge integer'              => [['id' => 'phone', 'value' => 6281234567890123456789]],
    'zero fraction'             => [['id' => 'ratio', 'value' => 2.0]],
    'empty list'                => [],
    'item without value'        => [['id' => 'title']],
    'item that is a string'     => ['loose', 'list'],
    'id is an array'            => [['id' => ['x'], 'value' => 'y']],
    'already key value'         => null,
];
// A utf8 (three byte) column cannot hold a raw emoji, so a database like that only ever held the escaped one.
$charset = (string) $pdo->query("SELECT character_set_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'entry_data' AND column_name = 'fields'")->fetchColumn();
if ($charset !== 'utf8mb4') {
    unset($awkward['emoji written raw']);
}
echo "entry_data.fields charset: $charset" . PHP_EOL;

$models = [];
$modelDefs = [
    ['Article', 'Content', '[]'], ['Product', 'Content', '["superadmin"]'], ['Page', 'Site', '[]'], ['Gallery', 'Media', '[]'],
    ['Event', 'Content', '["admin","superadmin"]'], ['Link', 'Site', '[]'], ['Report', 'Docs', '["developer"]'],
    ['Report', 'Docs', '[]'], // two live models with one name
    ['Trashed model', 'Content', '[]'], ['Empty model', 'Content', '[]'], ['Menu', 'Site', '[]'], ['Note', 'Content', '[]'],
];
$insModel = $pdo->prepare('INSERT INTO models (creator_id, created_at, updated_at, deleted_at, deleter_id) VALUES (1, ?, ?, ?, ?)');
$insModelData = $pdo->prepare('INSERT INTO model_data (model_id, name, fields, `group`, user_groups, icon, creator_id, created_at, updated_at, deleted_at, deleter_id) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)');
$modelWant = [];
$pdo->beginTransaction();
foreach ($modelDefs as $n => [$name, $group, $userGroups]) {
    $trashed = $name === 'Trashed model';
    $created = $date($t0, $t0 + 86400 * 30);
    $gone    = $trashed ? $date($t0 + 86400 * 400, $t1) : null;
    $insModel->execute([$created, $gone ?? $created, $gone, $trashed ? 1 : null]);
    $modelId = (int) $pdo->lastInsertId();
    $versions = mt_rand(1, 6);
    $liveIds  = [];
    $allIds   = [];
    for ($v = 1; $v <= $versions; $v++) {
        $definition = [
            ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['id' => 'body', 'label' => 'Body', 'type' => 'textarea'],
            ['id' => 'published_at', 'label' => 'Published', 'type' => 'datetime-local'],
            ['id' => 'related', 'label' => 'Related', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'entries', 'select' => 'id as value, fields as label']]],
        ];
        $when = $date($t0, $t1);
        $insModelData->execute([$modelId, $name . ($v > 1 ? " v$v" : ''), json_encode($definition, FLAGS), $group, $userGroups, 'file-text', $when, $when, $gone, $trashed ? 1 : null]);
        $id       = (int) $pdo->lastInsertId();
        $allIds[] = $id;
        if (! $trashed) {
            $liveIds[] = $id;
        }
    }
    $models[$modelId] = ['trashed' => $trashed, 'empty' => $name === 'Empty model'];
    // What the upgrade has to link, by the 0.1 rule. A trashed model gets its newest trashed version.
    $modelWant[$modelId] = $trashed ? max($allIds) : max($liveIds);
}
$pdo->commit();

$liveModels = array_keys(array_filter($models, fn(array $m): bool => ! $m['trashed'] && ! $m['empty']));
$insEntry   = $pdo->prepare('INSERT INTO entries (model_id, creator_id, created_at, updated_at, deleted_at, deleter_id) VALUES (?, 1, ?, ?, ?, ?)');
$insVersion = $pdo->prepare('INSERT INTO entry_data (entry_id, fields, creator_id, created_at, updated_at, deleted_at, deleter_id) VALUES (?, ?, 1, ?, ?, ?, ?)');

$versionWant = []; // version id => expected fields as a PHP value, for versions that must change
$entryWant   = []; // entry id => expected current version id, or null
$untouched   = []; // version id => the exact stored text, for versions that must not change
$kinds       = ['entries' => 0, 'versions' => 0, 'trashedEntries' => 0, 'big' => 0, 'awkward' => 0];

// The order of the entry loop follows how a CMS fills up, so ids are not sorted by model.
$awkwardNames = array_keys($awkward);
$awkwardSlot  = 0;
$heavyLeft    = 150; // a run of 1 MB bodies, such as a gallery with pictures pasted into the text
$bigLeft      = 25;  // single 3 MB bodies

// A stored text and the PHP value the upgrade should leave behind, for one old-format list.
$expect = function (array $list): array {
    $map = [];
    foreach ($list as $item) {
        if (! is_array($item) || ! array_key_exists('value', $item) || ! is_scalar($item['id'] ?? null)) {
            return [false, null]; // not convertible, left as it is
        }
        $map[(string) $item['id']] = $item['value'];
    }

    return [true, $map];
};

$pdo->beginTransaction();
for ($e = 1; $e <= $entryCount; $e++) {
    $modelId = $liveModels[mt_rand(0, count($liveModels) - 1)];
    $trashed = mt_rand(1, 100) <= 8;
    // A few entries sit in the trashed model or in no live model at all.
    if ($e % 997 === 0) {
        $modelId = (int) array_key_first(array_filter($models, fn(array $m): bool => $m['trashed']));
        $trashed = true;
    }
    $created = $date($t0, $t1);
    $gone    = $trashed ? $date(strtotime($created), $t1 + 86400) : null;
    $insEntry->execute([$modelId, $created, $gone ?? $created, $gone, $trashed ? 1 : null]);
    $entryId = (int) $pdo->lastInsertId();
    $kinds['entries']++;
    $kinds['trashedEntries'] += $trashed ? 1 : 0;

    $versions = mt_rand(1, 6);
    if ($e % 5 === 0) {
        $versions = 1;
    }
    $liveIds  = [];
    $goneIds  = [];
    for ($v = 1; $v <= $versions; $v++) {
        $when   = $date(strtotime($created), $t1);
        $custom = null;
        $list   = $ordinary($e);

        if ($heavyLeft > 0 && $e > 4000 && $e <= 4150) {
            $list[1]['value'] = str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. </p>', 16000);
            $heavyLeft--;
            $kinds['big']++;
        } elseif ($bigLeft > 0 && $e % 400 === 1) {
            $list[1]['value'] = 'data:image/png;base64,' . str_repeat('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJ', 80000);
            $bigLeft--;
            $kinds['big']++;
        } elseif ($e % 53 === 0) {
            $name = $awkwardNames[$awkwardSlot++ % count($awkwardNames)];
            $list = $awkward[$name];
            $custom = $name;
            $kinds['awkward']++;
        }

        if ($custom === 'already key value') {
            $text = json_encode(['title' => 'Already converted', 'views' => '5'], FLAGS);
            $mode = 'keep';
        } elseif ($custom === 'emoji written escaped') {
            $text = json_encode($list); // PHP's default, so non-ASCII text is written as \uXXXX
            [$ok, $want] = $expect($list);
            $mode = $ok ? 'convert' : 'odd';
        } else {
            $text = json_encode($list, FLAGS);
            [$ok, $want] = $expect($list);
            $mode = $list === [] ? 'keep' : ($ok ? 'convert' : 'odd');
        }

        // Old versions of a trashed entry were trashed with it. A live entry may have a trashed version now and then.
        $versionGone = $trashed ? $gone : (mt_rand(1, 40) === 1 && $v < $versions ? $date($t0, $t1) : null);
        $insVersion->execute([$entryId, $text, $when, $when, $versionGone, $versionGone !== null ? 1 : null]);
        $versionId = (int) $pdo->lastInsertId();
        $kinds['versions']++;
        if ($versionGone === null) {
            $liveIds[] = $versionId;
        } else {
            $goneIds[] = $versionId;
        }

        if ($mode === 'convert') {
            $versionWant[$versionId] = $want;
        } else {
            $untouched[$versionId] = $text;
        }
    }
    $all = array_merge($liveIds, $goneIds);
    $entryWant[$entryId] = $trashed ? ($goneIds !== [] ? max($goneIds) : max($all)) : ($liveIds !== [] ? max($liveIds) : null);

    if ($e % 2000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();

echo "built {$kinds['entries']} entries ({$kinds['trashedEntries']} trashed), {$kinds['versions']} versions ({$kinds['big']} of 1 to 3 MB, {$kinds['awkward']} awkward)" . PHP_EOL;

$stranded = count(array_filter($entryWant, fn($id): bool => $id === null));
echo "entries with only trashed versions that are not themselves trashed: $stranded" . PHP_EOL;

// ---------------------------------------------------------------- run the upgrade
$spark = function (string $arguments) use ($repo, $memory): array {
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $here = getcwd();
    chdir($repo);
    $start = microtime(true);
    exec('php -d memory_limit=' . $memory . " spark hyper:upgrade $arguments < $null 2>&1", $output, $code);
    chdir((string) $here);

    return [$code, implode("\n", $output), microtime(true) - $start];
};

[$code, $text, $seconds] = $spark('--dry-run');
$check('dry run exits 0', $code === 0, substr($text, -400));
echo sprintf("      dry run %.1f s at %s", $seconds, $memory) . PHP_EOL;

[$code, $text, $seconds] = $spark('--yes');
echo preg_replace('/^/m', '      | ', trim($text)) . PHP_EOL;
echo sprintf("      upgrade %.1f s at %s, exit %d", $seconds, $memory, $code) . PHP_EOL;
$check('the upgrade ran to the end', $code === 0 || $code === 1 && preg_match('/\d+ entry versions hold a list/', $text) === 1, "exit $code: " . substr($text, -300));

// ---------------------------------------------------------------- compare with what was generated
$wrongValue = $lost = $notChanged = [];
foreach ($rows('SELECT id, fields FROM entry_data ORDER BY id') as $row) {
    $id = (int) $row['id'];
    if (isset($versionWant[$id])) {
        $got = json_decode($row['fields'], true);
        if ($got === null && $row['fields'] !== 'null') {
            $wrongValue[] = $id;
        } elseif (json_encode($got) !== json_encode($versionWant[$id]) && $got != $versionWant[$id]) {
            $wrongValue[] = $id;
        }
    } elseif (isset($untouched[$id]) && $untouched[$id] !== $row['fields']) {
        $notChanged[] = $id;
    }
}
$total = (int) $pdo->query('SELECT COUNT(*) FROM entry_data')->fetchColumn();
$check('no version was lost or added', $total === $kinds['versions'], "$total of {$kinds['versions']}");
$check('every converted version holds its values', $wrongValue === [], count($wrongValue) . ' differ, first ids ' . implode(', ', array_slice($wrongValue, 0, 8)));
$check('versions that must stay as they are did', $notChanged === [], count($notChanged) . ' changed, first ids ' . implode(', ', array_slice($notChanged, 0, 8)));

if (! (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'entries' AND column_name = 'current_entry_data_id'")->fetchColumn()) {
    echo 'stress test: the upgrade did not get as far as linking entries, so the links were not checked' . PHP_EOL;
    exit(1);
}
$gotEntries = $pdo->query('SELECT id, current_entry_data_id FROM entries ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$badEntries = [];
foreach ($entryWant as $id => $want) {
    if (($gotEntries[$id] ?? null) !== ($want === null ? null : (string) $want) && (int) ($gotEntries[$id] ?? 0) !== (int) $want) {
        $badEntries[] = $id;
    }
}
$check('every entry points at the version the 0.1 rule gives', $badEntries === [], count($badEntries) . ' differ, first ids ' . implode(', ', array_slice($badEntries, 0, 8)));

$gotModels = $pdo->query('SELECT id, current_model_data_id FROM models ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$badModels = array_keys(array_filter($modelWant, fn($want, $id): bool => (int) ($gotModels[$id] ?? 0) !== $want, ARRAY_FILTER_USE_BOTH));
$check('every model points at the version the 0.1 rule gives', $badModels === [], implode(', ', $badModels));

$dates = $rows("SELECT COUNT(*) AS n FROM entry_data WHERE updated_at < created_at");
$check('no modified date moved before its created date', (int) $dates[0]['n'] === 0);

echo ($failed === 0 ? 'stress test passed' : "stress test: $failed checks failed") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
