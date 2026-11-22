<?php
declare(strict_types=1);

// Test `php spark hyper:upgrade` on a large database full of awkward data. Run it on a freshly restored database.
//   php stress.php <envFile> <repoDir> [--entries=12000] [--memory=128M]
//
// Nobody has handed over a real 0.1 database, so this builds one from assumptions about what such a database
// could hold: many entries with several versions each, big payloads, emoji, odd field shapes and inconsistent
// trash flags. It then upgrades it and checks every row of the new tables against what the generator put in:
// the models and entries in the hyper tables, each version's text, and each live entry's fields in StarDust.
// The old tables must come out of the upgrade unchanged, and a second run must change nothing.
//
// This script holds expected values as hashes only, so a run of 1 to 3 MB bodies does not fill its own memory.
require __DIR__ . '/lib.php';

if (count($argv) < 3) {
    fwrite(STDERR, "usage: stress.php <envFile> <repoDir> [--entries=N] [--memory=128M]\n");
    exit(2);
}
[, $envFile, $repo] = $argv;

// The conversion steps rewrite definitions and values after the copy, so what the database must hold is the generated data
// run through the same conversions, which are tested on arrays. This run proves they reach every model and every version.
require $repo . '/vendor/autoload.php';
$fieldTypes    = App\Fields\FieldTypes::core();
$convertFields = new App\Libraries\Upgrade\Convert\ModelFields($fieldTypes);
$convertValues = new App\Libraries\Upgrade\Convert\EntryValues($fieldTypes);
$entryCount = 12000;
$memory     = '128M';
foreach (array_slice($argv, 3) as $arg) {
    if (preg_match('/^--entries=(\d+)$/', $arg, $m) === 1) {
        $entryCount = (int) $m[1];
    } elseif (preg_match('/^--memory=(\S+)$/', $arg, $m) === 1) {
        $memory = $m[1];
    }
}
// The script lives under the same limit as the command, so a run that only passes on a big laptop is caught.
ini_set('memory_limit', $memory);
$pdo = connect(dbSettings($envFile), true);

const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

$failed = 0;
$check  = function (string $what, bool $ok, string $detail = '') use (&$failed): void {
    echo ($ok ? 'ok    ' : 'FAIL  ') . $what . ($ok || $detail === '' ? '' : "  ($detail)") . PHP_EOL;
    $failed += $ok ? 0 : 1;
};
/**
 * A problem found in the command or its libraries, kept out of the pass criteria. It prints `known` while the problem
 * is there, and `ok` with a note once it is fixed, so the label can then be removed.
 */
$known = function (string $what, bool $ok, string $label) use (&$knownSeen): void {
    $knownSeen += $ok ? 0 : 1;
    echo ($ok ? 'ok    ' : 'known ') . $what . ($ok ? '  (the known issue below seems fixed, remove its label)' : "  ($label)") . PHP_EOL;
};
$knownSeen = 0;
/** A check over a list of bad ids. */
$checkNone = function (string $what, array $bad) use ($check): void {
    $check($what, $bad === [], count($bad) . ' differ, first ids ' . implode(', ', array_slice($bad, 0, 8)));
};
$rows = fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/** The size in bytes of a PHP memory limit such as 128M. */
$bytesOf = function (string $limit): int {
    $n = (int) $limit;

    return match (strtoupper(substr(trim($limit), -1))) {
        'G'     => $n * 1073741824,
        'M'     => $n * 1048576,
        'K'     => $n * 1024,
        default => $n,
    };
};

/**
 * A string that is the same for two JSON values that hold the same content, whatever the key order or the number
 * spelling, because MySQL rewrites the text of a JSON column. An object is a stdClass or an array with keys that are
 * not 0, 1, 2 and so on.
 */
$canon = function (mixed $value) use (&$canon): string {
    if ($value instanceof stdClass || (is_array($value) && ! array_is_list($value))) {
        $map = is_array($value) ? $value : get_object_vars($value);
        ksort($map, SORT_STRING);
        $parts = [];
        foreach ($map as $key => $item) {
            $parts[] = json_encode((string) $key, JSON_UNESCAPED_UNICODE) . ':' . $canon($item);
        }

        return '{' . implode(',', $parts) . '}';
    }
    if (is_array($value)) {
        return '[' . implode(',', array_map($canon, $value)) . ']';
    }
    if (is_int($value) || is_float($value)) {
        return json_encode((float) $value);
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

/**
 * Row count and one hash over every row of a table. The hash is made by the server, one value per row, so a table of
 * 3 MB texts never travels through this script.
 *
 * @return array{0: int, 1: string}
 */
$tableHash = function (string $table) use ($pdo): array {
    $columns = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $parts   = implode(', ', array_map(static fn(string $c): string => "COALESCE(CAST(`$c` AS CHAR), '~null~')", $columns));
    $hash    = hash_init('md5');
    $count   = 0;
    $last    = 0;
    while (true) {
        $chunk = $pdo->query("SELECT id, MD5(CONCAT_WS('|', $parts)) FROM `$table` WHERE id > $last ORDER BY id LIMIT 4000")->fetchAll(PDO::FETCH_NUM);
        if ($chunk === []) {
            break;
        }
        foreach ($chunk as [$id, $rowHash]) {
            hash_update($hash, "$id:$rowHash;");
            $count++;
        }
        $last = (int) end($chunk)[0];
    }

    return [$count, hash_final($hash)];
};

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

// What the upgrade writes for an emoji, and for nothing else: a JSON escape pair.
$escapeEmoji = fn(string $json): string => (string) preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', static function (array $match): string {
    $offset = mb_ord($match[0], 'UTF-8') - 0x10000;

    return sprintf('\u%04x\u%04x', 0xD800 + ($offset >> 10), 0xDC00 + ($offset & 0x3FF));
}, $json);

$modelDefs = [
    ['Article', 'Content', '[]'], ['Product', 'Content', '["superadmin"]'], ['Page', 'Site', '[]'], ['Gallery', 'Media', '[]'],
    ['Event', 'Content', '["admin","superadmin"]'], ['Link', 'Site', '[]'], ['Report', 'Docs', '["developer"]'],
    ['Report', 'Docs', '[]'], // two live models with one name
    ['Trashed model', 'Content', '[]'], ['Empty model', 'Content', '[]'], ['Menu', 'Site', '[]'], ['Note', 'Content', '[]'],
];
$insModel = $pdo->prepare('INSERT INTO models (creator_id, created_at, updated_at, deleted_at, deleter_id) VALUES (1, ?, ?, ?, ?)');
$insModelData = $pdo->prepare('INSERT INTO model_data (model_id, name, fields, `group`, user_groups, icon, creator_id, created_at, updated_at, deleted_at, deleter_id) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)');

$models        = []; // model id => ['trashed', 'empty', 'created', 'updated', 'deleted', 'current' (revision id)]
$modelRevision = []; // model_data id => model id, name, group, user_groups, md5 of the definition, created_at
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
        $when     = $date($t0, $t1);
        $title    = $name . ($v > 1 ? " v$v" : '');
        $json     = json_encode($definition, FLAGS);
        $jsonV2     = json_encode($convertFields->convert($definition, [])['fields'], FLAGS);
        $stressDefs = $convertFields->convert($definition, [])['fields'];
        $insModelData->execute([$modelId, $title, $json, $group, $userGroups, 'file-text', $when, $when, $gone, $trashed ? 1 : null]);
        $id       = (int) $pdo->lastInsertId();
        $allIds[] = $id;
        $modelRevision[$id] = ['model' => $modelId, 'name' => $title, 'group' => $group, 'groups' => $userGroups, 'hash' => md5($jsonV2), 'when' => $when];
        if (! $trashed) {
            $liveIds[] = $id;
        }
    }
    // What the upgrade has to link, by the 0.1 rule. A trashed model gets its newest trashed version.
    $models[$modelId] = [
        'trashed' => $trashed, 'empty' => $name === 'Empty model', 'created' => $created, 'updated' => $gone ?? $created,
        'deleted' => $gone, 'current' => $trashed ? max($allIds) : max($liveIds), 'versions' => count($allIds),
    ];
}
$pdo->commit();

$liveModels = array_keys(array_filter($models, fn(array $m): bool => ! $m['trashed'] && ! $m['empty']));
$insEntry   = $pdo->prepare('INSERT INTO entries (model_id, creator_id, created_at, updated_at, deleted_at, deleter_id) VALUES (?, 1, ?, ?, ?, ?)');
$insVersion = $pdo->prepare('INSERT INTO entry_data (entry_id, fields, creator_id, created_at, updated_at, deleted_at, deleter_id) VALUES (?, ?, 1, ?, ?, ?, ?)');

$packet = (int) $pdo->query('SELECT @@max_allowed_packet')->fetchColumn();

// What every version must hold afterwards. Only hashes and sizes, never the text.
//   entry, mode (convert, keep or odd), hash and size of the converted text, hash of the canonical fields, created_at,
//   and 1 for a version that holds a whole number written with a fraction, which the command's verifier cannot compare
$versionWant = [];
$entryWant   = []; // entry id => model, created, updated, deleted, deleter, current version id, trashed
$kinds       = ['entries' => 0, 'versions' => 0, 'trashedEntries' => 0, 'big' => 0, 'awkward' => 0, 'duplicate' => 0, 'oddVersions' => 0];

// The order of the entry loop follows how a CMS fills up, so ids are not sorted by model.
$awkwardNames = array_keys($awkward);
$awkwardSlot  = 0;
$heavyLeft    = 150; // a run of 1 MB bodies, such as a gallery with pictures pasted into the text
$bigLeft      = 25;  // single 3 MB bodies

// Whether an old-format list converts, the map it converts to, and whether it names an id twice.
$expect = function (array $list): array {
    $map   = [];
    $twice = false;
    foreach ($list as $item) {
        if (! is_array($item) || ! array_key_exists('value', $item) || ! is_scalar($item['id'] ?? null)) {
            return [false, null, false]; // not convertible, left as it is
        }
        $twice = $twice || array_key_exists((string) $item['id'], $map);
        $map[(string) $item['id']] = $item['value'];
    }

    return [true, $map, $twice];
};

$pdo->beginTransaction();
for ($e = 1; $e <= $entryCount; $e++) {
    $modelId = $liveModels[mt_rand(0, count($liveModels) - 1)];
    $trashed = mt_rand(1, 100) <= 8;
    // A few entries sit in the trashed model.
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

        $twice = false;
        $want  = null;
        if ($custom === 'already key value') {
            $text = json_encode(['title' => 'Already converted', 'views' => '5'], FLAGS);
            $mode = 'keep';
        } elseif ($custom === 'emoji written escaped') {
            $text = json_encode($list); // PHP's default, so non-ASCII text is written as \uXXXX
            [$ok, $want, $twice] = $expect($list);
            $mode = $ok ? 'convert' : 'odd';
        } else {
            $text = json_encode($list, FLAGS);
            [$ok, $want, $twice] = $expect($list);
            $mode = $list === [] ? 'keep' : ($ok ? 'convert' : 'odd');
        }

        // The text the upgrade must leave in hyper_entry_revisions, and the fields StarDust must hold for it.
        if ($mode === 'convert') {
            $want      = $convertValues->convert((array) $want, $stressDefs)['fields'];
            $converted = $escapeEmoji(json_encode((object) $want, FLAGS));
            $fieldHash = md5($canon((object) $want));
        } else {
            $converted = $text;
            $fieldHash = $mode === 'odd' ? null : md5($text === '[]' ? '{}' : $canon(json_decode($text)));
        }
        $kinds['duplicate']   += $twice ? 1 : 0;
        $kinds['oddVersions'] += $mode === 'odd' ? 1 : 0;

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

        $versionWant[$versionId] = [$entryId, $mode, md5($converted), strlen($converted), $fieldHash, $when, $custom === 'zero fraction' ? 1 : 0];
        unset($text, $converted, $list);
    }
    $all = array_merge($liveIds, $goneIds);
    $entryWant[$entryId] = [
        'model' => $modelId, 'created' => $created, 'updated' => $gone ?? $created, 'deleted' => $gone, 'deleter' => $trashed ? 1 : null,
        'current' => $trashed ? ($goneIds !== [] ? max($goneIds) : max($all)) : ($liveIds !== [] ? max($liveIds) : max($all)), 'trashed' => $trashed,
    ];

    if ($e % 2000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();

echo "built {$kinds['entries']} entries ({$kinds['trashedEntries']} trashed), {$kinds['versions']} versions ({$kinds['big']} of 1 to 3 MB, {$kinds['awkward']} awkward)" . PHP_EOL;

// What the upgrade should do with each live entry. An entry whose current text is not an object, or too big for the
// packet by the command's own margin, is not written and must be named by the command.
$skipWant   = []; // entry id => 'not an object' or 'packet'
$floatWant  = []; // entry id => true, for written entries with a whole number written as 2.0
$blankWant  = []; // entry id => true, for written entries whose fields are blank
$writeWant  = []; // entry id => true, for live entries that go to StarDust
$modelCount = []; // model id => ['live', 'trashed', 'written']
foreach ($models as $id => $unused) {
    $modelCount[$id] = ['live' => 0, 'trashed' => 0, 'written' => 0];
}
foreach ($entryWant as $id => $entry) {
    $count = &$modelCount[$entry['model']];
    if ($entry['trashed']) {
        $count['trashed']++;
        unset($count);
        continue;
    }
    $count['live']++;
    [, $mode, , $size] = $versionWant[$entry['current']];
    if ($size * 2 >= $packet) {
        $skipWant[$id] = 'packet';
    } elseif ($mode === 'odd') {
        $skipWant[$id] = 'not an object';
    } else {
        $writeWant[$id] = true;
        $count['written']++;
        if ($versionWant[$entry['current']][6] === 1) {
            $floatWant[$id] = true;
        }
        if ($versionWant[$entry['current']][4] === md5('{}')) {
            $blankWant[$id] = true;
        }
    }
    unset($count);
}
echo 'live entries that the command must refuse: ' . count($skipWant) . " (max_allowed_packet " . round($packet / 1048576) . ' MB)' . PHP_EOL;

$oldBefore = [];
foreach (['models', 'model_data', 'entries', 'entry_data'] as $table) {
    $oldBefore[$table] = $tableHash($table);
}

// ---------------------------------------------------------------- run the upgrade
$peakFile = tempnam(sys_get_temp_dir(), 'peak');
$prepend  = tempnam(sys_get_temp_dir(), 'prep');
file_put_contents($prepend, '<?php register_shutdown_function(static function (): void { file_put_contents(' . var_export($peakFile, true) . ', memory_get_peak_usage(true) . " " . memory_get_peak_usage(false)); });');
register_shutdown_function(static function () use ($peakFile, $prepend): void {
    @unlink($peakFile);
    @unlink($prepend);
});

/** @return array{0: int, 1: string, 2: float, 3: int} Exit code, output with colours removed, seconds, peak bytes PHP took from the system. */
$spark = function (string $arguments) use ($repo, $memory, $peakFile, $prepend): array {
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $here = getcwd();
    chdir($repo);
    file_put_contents($peakFile, '');
    $start = microtime(true);
    $output = [];
    exec('php -d memory_limit=' . $memory . ' -d auto_prepend_file="' . $prepend . '" spark hyper:upgrade ' . $arguments . " < $null 2>&1", $output, $code);
    $seconds = microtime(true) - $start;
    chdir((string) $here);

    $peak = (int) explode(' ', (string) file_get_contents($peakFile) . ' ')[0];

    return [$code, (string) preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $output)), $seconds, $peak];
};
$mb      = fn(int $bytes): string => round($bytes / 1048576, 1) . ' MB';
$limit   = $bytesOf($memory);
$noCrash = fn(string $text): bool => preg_match('/Fatal error|Allowed memory size|Out of memory|Uncaught/i', $text) !== 1;

[$code, $text, $seconds, $peak] = $spark('--dry-run');
$check('dry run exits 0', $code === 0, substr($text, -400));
$check('dry run finds the 0.1 layout', str_contains($text, 'Layout found: HyperCMS 0.1'));
$check('dry run does not run out of memory', $noCrash($text), substr($text, -300));
$seen = [];
if (preg_match_all('/^\s+(\d+) .*: (\d+) live, (\d+) trashed$/m', $text, $found, PREG_SET_ORDER) > 0) {
    foreach ($found as $f) {
        $seen[(int) $f[1]] = [(int) $f[2], (int) $f[3]];
    }
}
$wrong = [];
foreach ($modelCount as $id => $c) {
    if ($c['live'] + $c['trashed'] > 0 && ($seen[$id] ?? null) !== [$c['live'], $c['trashed']]) {
        $wrong[] = $id;
    }
}
$checkNone('dry run counts live and trashed entries per model', $wrong);
$check('dry run counts the odd versions', preg_match('/hold a list in some other shape and are kept as text only: ' . $kinds['oddVersions'] . ' /', $text) === 1 || $kinds['oddVersions'] === 0, 'expected ' . $kinds['oddVersions']);
$check('dry run created no hyper tables', (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'hyper_entries'")->fetchColumn() === 0);
echo sprintf('      dry run %.1f s at %s, PHP peak %s', $seconds, $memory, $mb($peak)) . PHP_EOL;

[$code, $text, $seconds, $peak] = $spark('--yes');
echo preg_replace('/^/m', '      | ', trim(preg_replace('/^(Entry \d+ was not written.*\n?){6,}/m', "(entry lines left out here)\n", $text))) . PHP_EOL;
echo sprintf('      upgrade %.1f s at %s, PHP peak %s of %s, exit %d', $seconds, $memory, $mb($peak), $mb($limit), $code) . PHP_EOL;
$upgradeSeconds = $seconds;
$upgradePeak    = $peak;

$check('the upgrade does not run out of memory', $noCrash($text) && $peak > 0 && $peak < $limit, 'peak ' . $mb($peak));
$wantExit = $skipWant === [] ? 0 : 1;
$check("the upgrade exits $wantExit, as the entries it must refuse say", $code === $wantExit, "exit $code: " . substr($text, -300));
$check('the upgrade says it copied every model', preg_match('/Copied (\d+) models and (\d+) model versions\./', $text, $m) === 1 && (int) $m[1] === count($models) && (int) $m[2] === count($modelRevision), $m[0] ?? 'no line');
$check('the upgrade registered every model in StarDust', preg_match('/Registered (\d+) models in StarDust\./', $text, $m) === 1 && (int) $m[1] === count($models), $m[0] ?? 'no line');
$check('the upgrade says it copied every entry and version', preg_match('/Copied (\d+) entries and (\d+) entry versions\./', $text, $m) === 1 && (int) $m[1] === $kinds['entries'] && (int) $m[2] === $kinds['versions'], $m[0] ?? 'no line');
$check('the upgrade says how many entries it wrote', preg_match('/Wrote (\d+) entries to StarDust\./', $text, $m) === 1 && (int) $m[1] === count($writeWant), ($m[0] ?? 'no line') . ', expected ' . count($writeWant));
$check('versions that name one field twice are reported', $kinds['duplicate'] === 0 ? ! str_contains($text, 'named one field id more than once') : str_contains($text, "{$kinds['duplicate']} entry versions named one field id more than once"), 'expected ' . $kinds['duplicate']);
$check('versions in another shape are reported', $kinds['oddVersions'] === 0 ? ! str_contains($text, 'in some other shape and were copied') : str_contains($text, "{$kinds['oddVersions']} entry versions hold a list in some other shape"), 'expected ' . $kinds['oddVersions']);

// The entries the command lists as not written are exactly the ones the generator made unwritable.
$listed = [];
if (preg_match_all('/^Entry (\d+) was not written, because (.*)$/m', $text, $found, PREG_SET_ORDER) > 0) {
    foreach ($found as $f) {
        $listed[(int) $f[1]] = $f[2];
    }
}
$unexpected = array_merge(array_diff_key($listed, $skipWant), array_diff_key($skipWant, $listed));
$wrongWhy   = [];
foreach ($skipWant as $id => $why) {
    $word = $why === 'packet' ? 'max_allowed_packet' : 'not an object';
    if (isset($listed[$id]) && ! str_contains($listed[$id], $word)) {
        $wrongWhy[] = $id;
    }
}
$checkNone('the entries the command lists as not written are the ones the generator made unwritable', array_keys($unexpected));
$checkNone('each refused entry is refused for the expected reason', $wrongWhy);
// The command's verifier compares an entry's StarDust fields with its converted source.
$differ = preg_match('/(\d+) entries differ from their source or did not come back\./', $text, $m) === 1 ? (int) $m[1] : 0;
$check('the verifier of the command reports no entry as differing, a zero fraction number such as 2.0 included', $differ === 0, "$differ reported, " . count($floatWant) . ' entries have a zero fraction number');
$check('the command found no live entry without a StarDust entry left unexplained', ! preg_match('/live entries still have no StarDust entry/', $text));

// ---------------------------------------------------------------- the old tables stay as they were
$moved = [];
foreach (['models', 'model_data', 'entries', 'entry_data'] as $table) {
    $after = $tableHash($table);
    if ($after !== $oldBefore[$table]) {
        $moved[] = "$table ({$oldBefore[$table][0]} rows before, {$after[0]} after)";
    }
}
$check('the old tables are unchanged, by row count and a hash of every row', $moved === [], implode(', ', $moved));

// ---------------------------------------------------------------- models
$hyperModels = [];
foreach ($rows('SELECT id, sd_model_id, name, `group`, icon, user_groups, current_revision_id, creator_id, editor_id, deleter_id, created_at, updated_at, deleted_at, MD5(fields) AS fields_hash FROM hyper_models ORDER BY id') as $row) {
    $hyperModels[(int) $row['id']] = $row;
}
$bad = [];
foreach ($models as $id => $want) {
    $got  = $hyperModels[$id] ?? null;
    $rev  = $modelRevision[$want['current']];
    if ($got === null
        || (int) $got['current_revision_id'] !== $want['current'] || $got['name'] !== $rev['name'] || $got['group'] !== $rev['group']
        || $got['user_groups'] !== $rev['groups'] || $got['icon'] !== 'file-text' || $got['fields_hash'] !== $rev['hash']
        || (int) $got['creator_id'] !== 1 || (int) $got['editor_id'] !== 1
        || $got['created_at'] !== $want['created'] || $got['updated_at'] !== $want['updated'] || $got['deleted_at'] !== $want['deleted']
        || ($want['trashed'] ? (int) $got['deleter_id'] !== 1 : $got['deleter_id'] !== null)) {
        $bad[] = $id;
    }
}
$check('every old model is in hyper_models, no more', count($hyperModels) === count($models), count($hyperModels) . ' of ' . count($models));
$checkNone('every model keeps its id, name, current version, creator, deleter, form definition and timestamps', $bad);

$bad = [];
$seen = 0;
foreach ($rows('SELECT id, model_id, name, `group`, user_groups, creator_id, created_at, MD5(fields) AS fields_hash FROM hyper_model_revisions ORDER BY id') as $row) {
    $seen++;
    $want = $modelRevision[(int) $row['id']] ?? null;
    if ($want === null || (int) $row['model_id'] !== $want['model'] || $row['name'] !== $want['name'] || $row['group'] !== $want['group'] || $row['user_groups'] !== $want['groups']
        || $row['fields_hash'] !== $want['hash'] || (int) $row['creator_id'] !== 1 || $row['created_at'] !== $want['when']) {
        $bad[] = (int) $row['id'];
    }
}
$check('every old model version is in hyper_model_revisions with its id, no more', $seen === count($modelRevision), "$seen of " . count($modelRevision));
$checkNone('every model version keeps its model, name, definition, creator and date', $bad);

$sdModels = $pdo->query("SELECT name, id, tenant_id FROM sd_stardust_models")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
$bad      = [];
$sdIds    = [];
foreach ($models as $id => $unused) {
    $sd = $hyperModels[$id]['sd_model_id'] ?? null;
    $sdIds[$id] = $sd === null ? null : (int) $sd;
    if ($sd === null || (int) ($sdModels["m$id"]['id'] ?? 0) !== (int) $sd || (int) ($sdModels["m$id"]['tenant_id'] ?? 0) !== 1) {
        $bad[] = $id;
    }
}
$checkNone('every model is registered in StarDust as m{id} and points at it', $bad);
$fieldNames = [];
foreach ($pdo->query('SELECT model_id, name, declared_type, is_filterable FROM sd_stardust_fields WHERE deleted_at IS NULL ORDER BY model_id, name')->fetchAll(PDO::FETCH_ASSOC) as $f) {
    $fieldNames[(int) $f['model_id']][] = $f['name'] . ':' . $f['declared_type'] . ':' . $f['is_filterable'];
}
$bad = [];
foreach ($models as $id => $unused) {
    if (($fieldNames[$sdIds[$id] ?? 0] ?? []) !== ['body:string:0', 'published_at:string:0', 'related:string:0', 'title:string:0']) {
        $bad[] = $id;
    }
}
$checkNone('each model has its four form fields in StarDust, as plain strings', $bad);

// ---------------------------------------------------------------- entries
$bad   = [];
$seen  = 0;
$last  = 0;
$sdSeen = [];
$badBytes = $badSd = $badTrashed = [];
while (true) {
    $chunk = $rows("SELECT id, model_id, sd_entry_id, current_revision_id, creator_id, editor_id, deleter_id, created_at, updated_at, deleted_at, payload_bytes FROM hyper_entries WHERE id > $last ORDER BY id LIMIT 4000");
    if ($chunk === []) {
        break;
    }
    foreach ($chunk as $row) {
        $id    = (int) $row['id'];
        $last  = $id;
        $seen++;
        $want  = $entryWant[$id] ?? null;
        if ($want === null
            || (int) $row['model_id'] !== $want['model'] || (int) $row['current_revision_id'] !== $want['current']
            || (int) $row['creator_id'] !== 1 || (int) $row['editor_id'] !== 1
            || $row['created_at'] !== $want['created'] || $row['updated_at'] !== $want['updated'] || $row['deleted_at'] !== $want['deleted']
            || ($want['trashed'] ? (int) $row['deleter_id'] !== 1 : $row['deleter_id'] !== null)) {
            $bad[] = $id;
            continue;
        }
        if ($want['trashed']) {
            if ($row['sd_entry_id'] !== null || (int) $row['payload_bytes'] !== 0) {
                $badTrashed[] = $id;
            }
        } elseif (isset($writeWant[$id])) {
            if ($row['sd_entry_id'] === null) {
                $badSd[] = $id;
            } else {
                $sdSeen[$id] = (int) $row['sd_entry_id'];
            }
            if ((int) $row['payload_bytes'] !== $versionWant[$want['current']][3]) {
                $badBytes[] = $id;
            }
        } elseif ($row['sd_entry_id'] !== null || (int) $row['payload_bytes'] !== 0) {
            $badSd[] = $id;
        }
    }
}
$check('every old entry is in hyper_entries with its id, no more', $seen === count($entryWant), "$seen of " . count($entryWant));
$checkNone('every entry keeps its model, current version, creator, editor, deleter and timestamps', $bad);
$checkNone('trashed entries have no StarDust entry and no payload size', $badTrashed);
$checkNone('every live entry that was written has sd_entry_id, and refused ones have none', $badSd);
$checkNone('payload_bytes is the length of the converted text of each written entry', $badBytes);
$check('sd_entry_id is never used twice', count(array_unique($sdSeen)) === count($sdSeen));

// ---------------------------------------------------------------- versions
$bad = $badText = $notCleared = $clearedTooSoon = [];
$seen = 0;
$last = 0;
while (true) {
    $chunk = $rows("SELECT id, entry_id, creator_id, created_at, fields IS NULL AS blank, LENGTH(fields) AS size, MD5(fields) AS hash FROM hyper_entry_revisions WHERE id > $last ORDER BY id LIMIT 4000");
    if ($chunk === []) {
        break;
    }
    foreach ($chunk as $row) {
        $id   = (int) $row['id'];
        $last = $id;
        $seen++;
        $want = $versionWant[$id] ?? null;
        if ($want === null || (int) $row['entry_id'] !== $want[0] || (int) $row['creator_id'] !== 1 || $row['created_at'] !== $want[5]) {
            $bad[] = $id;
            continue;
        }
        $entry   = $entryWant[$want[0]];
        $cleared = $entry['current'] === $id && isset($writeWant[$want[0]]);
        if ($cleared) {
            if (! (int) $row['blank']) {
                $notCleared[] = $id;
            }
        } elseif ((int) $row['blank']) {
            $clearedTooSoon[] = $id;
        } elseif ($row['hash'] !== $want[2] || (int) $row['size'] !== $want[3]) {
            $badText[] = $id;
        }
    }
}
$check('every old version is in hyper_entry_revisions with its id, no more', $seen === $kinds['versions'], "$seen of {$kinds['versions']}");
$checkNone('every version keeps its entry, creator and created date', $bad);
$checkNone('every history and trash snapshot holds the converted text (object format, last value wins, odd shapes as they were, emoji as escape pairs)', $badText);
$checkNone('the current version of every written entry has NULL fields', $notCleared);
$checkNone('no version lost its text unless it is the current one of a written entry', $clearedTooSoon);

// ---------------------------------------------------------------- StarDust
// Every written entry's fields, read back a chunk at a time and compared as decoded, key sorted content.
$badFields = $badModel = [];
$ids       = [];
$bytes     = 0;
$blankAsList = [];
$compare   = function () use (&$ids, &$bytes, $pdo, &$badFields, &$badModel, &$blankAsList, $versionWant, $entryWant, $sdIds, $canon): void {
    if ($ids === []) {
        return;
    }
    $statement = $pdo->query('SELECT id, tenant_id, model_id, deleted_at, fields FROM sd_entry_data WHERE id IN (' . implode(',', array_values($ids)) . ')');
    $got = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $got[(int) $row['id']] = $row;
    }
    foreach ($ids as $entryId => $sdId) {
        $row = $got[$sdId] ?? null;
        $want = $entryWant[$entryId];
        if ($row === null || (int) $row['tenant_id'] !== 1 || (int) $row['model_id'] !== $sdIds[$want['model']] || $row['deleted_at'] !== null) {
            $badModel[] = $entryId;
            continue;
        }
        $fields = json_decode((string) $row['fields']);
        if ($fields === [] && $versionWant[$want['current']][4] === md5('{}')) {
            $blankAsList[] = $entryId; // StarDust stores an empty array as [], which is a blank entry
            continue;
        }
        if (! $fields instanceof stdClass || md5($canon($fields)) !== $versionWant[$want['current']][4]) {
            $badFields[] = $entryId;
        }
    }
    $ids   = [];
    $bytes = 0;
};
foreach ($sdSeen as $entryId => $sdId) {
    $size = $versionWant[$entryWant[$entryId]['current']][3];
    if ($ids !== [] && ($bytes + $size > 4 * 1048576 || count($ids) >= 500)) {
        $compare();
    }
    $ids[$entryId] = $sdId;
    $bytes += $size;
}
$compare();
$checkNone('every written entry is in StarDust under its model and tenant', $badModel);
$checkNone('every written entry\'s StarDust fields equal the converted source (blank reads as {})', $badFields);

// StarDust stores a blank entry's empty array as []. The row assembly of the app writes it as {}, so only the content is checked here.

$sdRows = (int) $pdo->query('SELECT COUNT(*) FROM sd_entry_data WHERE tenant_id = 1')->fetchColumn();
$check('StarDust holds one entry per written entry and nothing else', $sdRows === count($writeWant), "$sdRows rows for " . count($writeWant) . ' written entries');

// Counts per model.
$hyperLive = $pdo->query('SELECT model_id, SUM(deleted_at IS NULL), SUM(deleted_at IS NOT NULL), SUM(sd_entry_id IS NOT NULL) FROM hyper_entries GROUP BY model_id')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_NUM);
$sdCount   = $pdo->query('SELECT model_id, COUNT(*) FROM sd_entry_data WHERE tenant_id = 1 GROUP BY model_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$bad       = [];
foreach ($modelCount as $id => $c) {
    $h = $hyperLive[$id] ?? [0, 0, 0];
    if ((int) $h[0] !== $c['live'] || (int) $h[1] !== $c['trashed'] || (int) $h[2] !== $c['written'] || (int) ($sdCount[$sdIds[$id] ?? 0] ?? 0) !== $c['written']) {
        $bad[] = $id;
    }
}
$checkNone('live, trashed and written counts per model match, in hyper_entries and in StarDust', $bad);

// The command's own table of entries per model.
$printed = [];
if (preg_match_all('/^\s+(\d+) .*: (\d+) written, (\d+) read back$/m', $text, $found, PREG_SET_ORDER) > 0) {
    foreach ($found as $f) {
        $printed[(int) $f[1]] = [(int) $f[2], (int) $f[3]];
    }
}
$bad = [];
foreach ($modelCount as $id => $c) {
    if (($printed[$id] ?? null) !== [$c['written'], $c['written']]) {
        $bad[] = $id;
    }
}
$checkNone('the command\'s own entries per model table shows every written entry read back', $bad);

// ---------------------------------------------------------------- a second run
$tables = ['hyper_models', 'hyper_model_revisions', 'hyper_entries', 'hyper_entry_revisions', 'sd_entry_data', 'sd_stardust_models', 'sd_stardust_fields'];
$state  = function () use ($tables, $tableHash): array {
    $out = [];
    foreach ($tables as $table) {
        $out[$table] = $tableHash($table);
    }

    return $out;
};
$settled = $state();
[$code2, $text2, $seconds2, $peak2] = $spark('--yes');
echo sprintf('      second run %.1f s, PHP peak %s, exit %d', $seconds2, $mb($peak2), $code2) . PHP_EOL;
$check('a second run does not run out of memory', $noCrash($text2) && $peak2 > 0 && $peak2 < $limit, 'peak ' . $mb($peak2));
$check("a second run exits $wantExit again", $code2 === $wantExit, "exit $code2: " . substr($text2, -300));
$check('a second run copies and writes nothing', preg_match('/Copied 0 models and 0 model versions\./', $text2) === 1 && preg_match('/Registered 0 models/', $text2) === 1 && preg_match('/Copied 0 entries and 0 entry versions\./', $text2) === 1 && preg_match('/Wrote 0 entries/', $text2) === 1, substr($text2, 0, 600));
$after = $state();
$check('a second run changes no row of the hyper or StarDust tables', $after === $settled, implode(', ', array_keys(array_filter($after, fn($v, $k): bool => $v !== $settled[$k], ARRAY_FILTER_USE_BOTH))));
$listed2 = [];
if (preg_match_all('/^Entry (\d+) was not written/m', $text2, $found) > 0) {
    $listed2 = array_map('intval', $found[1]);
}
sort($listed2);
$expected2 = array_keys($skipWant);
sort($expected2);
$check('a second run names the same entries as not written', $listed2 === $expected2);
$moved = [];
foreach (['models', 'model_data', 'entries', 'entry_data'] as $table) {
    if ($tableHash($table) !== $oldBefore[$table]) {
        $moved[] = $table;
    }
}
$check('the old tables are still unchanged after the second run', $moved === [], implode(', ', $moved));

echo "known issues seen: $knownSeen (not counted as failures)" . PHP_EOL;
echo sprintf('upgrade took %.1f s, peak %s of the %s limit; this script peaked at %s', $upgradeSeconds, $mb($upgradePeak), $memory, $mb(memory_get_peak_usage(true))) . PHP_EOL;
echo ($failed === 0 ? 'stress test passed' : "stress test: $failed checks failed") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
