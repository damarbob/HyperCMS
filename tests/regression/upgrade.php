<?php
declare(strict_types=1);

// Test `php spark hyper:upgrade`. Run it on a freshly restored database.
//   php upgrade.php <envFile> <repoDir>
//
// The seed is in the 0.2 layout, so the script first records what it holds, then puts it back into the
// shape HyperCMS 0.1 left behind, upgrades it, and checks that every row came back as the seed had it,
// in the hyper_ tables and in StarDust 0.3.
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
$rows  = fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$byId  = fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
$pairs = fn(string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
$tables = fn(): array => $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

/** A fingerprint of every row of one table. The order of rows does not matter. */
$tableHash = function (string $table) use ($pdo): string {
    $lines = [];
    foreach ($pdo->query("SELECT * FROM `$table`", PDO::FETCH_ASSOC) as $row) {
        $lines[] = json_encode($row);
    }
    sort($lines);

    return count($lines) . ':' . md5(implode("\n", $lines));
};
/** @param list<string> $names */
$hashes = function (array $names) use ($tableHash): array {
    $out = [];
    foreach ($names as $name) {
        $out[$name] = $tableHash($name);
    }

    return $out;
};
/** Fingerprints of the four old tables, with their definitions, so a changed column counts too. */
$oldTables = function () use ($hashes, $pdo): array {
    $out = $hashes(['entry_data', 'entries', 'model_data', 'models']);
    foreach (['entry_data', 'entries', 'model_data', 'models'] as $table) {
        $out["$table definition"] = md5((string) $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1]);
    }

    return $out;
};
/** Every table the upgrade adds or fills. */
$newTables = fn(): array => $hashes(array_values(array_filter($tables(), fn(string $t): bool => str_starts_with($t, 'hyper_') || str_starts_with($t, 'sd_'))));

/** Sort the keys of every object, so two payloads compare by content, and write them as one string. */
$canon = function (mixed $value) use (&$canon): mixed {
    // MySQL keeps 2.0 as the integer 2, so a number with no fraction is compared as an integer.
    if (is_float($value) && is_finite($value) && $value === floor($value) && abs($value) < 9.0E15) {
        return (int) $value;
    }
    if (! is_array($value)) {
        return $value;
    }
    $value = array_map($canon, $value);
    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
};
/** A payload as sorted JSON text. Blank and missing payloads read as `{}`. */
$payload = function (?string $json) use ($canon): string {
    $data = json_decode((string) $json, true);
    $data = is_array($data) && $data !== [] ? $canon($data) : new stdClass();

    return json_encode($data, FLAGS);
};

/** The fields of one entry version as an object, whichever of the two formats it is stored in. */
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
$isOldFormat = function (string $json): bool {
    $data = json_decode($json);

    return is_array($data) && $data !== [] && $data[0] instanceof stdClass && property_exists($data[0], 'id');
};

$spark = function (string $arguments) use ($repo): array {
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $here = getcwd();
    chdir($repo);
    exec("php -d memory_limit=128M spark hyper:upgrade $arguments < $null 2>&1", $output, $code);
    chdir((string) $here);

    return [$code, implode("\n", $output)];
};

$columnExists = fn(string $table, string $column): bool => $rows("SHOW COLUMNS FROM `$table` LIKE '$column'") !== [];
$indexExists  = fn(string $table, string $index): bool => $rows("SHOW INDEX FROM `$table` WHERE Key_name = '$index'") !== [];

// ---------------------------------------------------------------------------------------------
// Models and entries in every shape the old form could store, added to the seed before it is recorded. They are kept
// out of seed.sql so the captures of the API and the admin keep describing the same data.

$stamp = '2026-11-15 10:00:00';
foreach (require __DIR__ . '/old_shapes.php' as $shape) {
    $pdo->prepare('INSERT INTO models (creator_id, created_at, updated_at) VALUES (1, ?, ?)')->execute([$stamp, $stamp]);
    $modelId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO model_data (model_id, name, fields, `group`, user_groups, icon, creator_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)')
        ->execute([$modelId, $shape['name'], json_encode($shape['fields'], FLAGS), 'Content', json_encode($shape['user_groups'] ?? [], FLAGS), 'file-text', $stamp, $stamp]);
    $pdo->prepare('UPDATE models SET current_model_data_id = ? WHERE id = ?')->execute([(int) $pdo->lastInsertId(), $modelId]);

    foreach ($shape['entries'] as $values) {
        $pdo->prepare('INSERT INTO entries (model_id, creator_id, created_at, updated_at) VALUES (?, 1, ?, ?)')->execute([$modelId, $stamp, $stamp]);
        $entryId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO entry_data (entry_id, fields, creator_id, created_at, updated_at) VALUES (?, ?, 1, ?, ?)')->execute([$entryId, json_encode($values, FLAGS), $stamp, $stamp]);
        $pdo->prepare('UPDATE entries SET current_entry_data_id = ? WHERE id = ?')->execute([(int) $pdo->lastInsertId(), $entryId]);
    }
}

// ---------------------------------------------------------------------------------------------
// What the seed holds, before anything is changed. This is what the upgrade has to arrive at.

$wantModels = $byId(
    'SELECT m.id, d.name, d.fields, d.`group`, d.icon, d.user_groups, m.current_model_data_id AS current_revision_id,
        m.creator_id, d.creator_id AS editor_id, m.deleter_id, m.created_at, m.updated_at, m.deleted_at
    FROM models m JOIN model_data d ON d.id = m.current_model_data_id ORDER BY m.id'
);
$wantModelRevisions = $byId('SELECT id, model_id, name, fields, `group`, icon, user_groups, creator_id, created_at FROM model_data ORDER BY id');
$wantEntries        = $byId(
    'SELECT e.id, e.model_id, e.current_entry_data_id AS current_revision_id, e.creator_id, d.creator_id AS editor_id,
        e.deleter_id, e.created_at, e.updated_at, e.deleted_at
    FROM entries e JOIN entry_data d ON d.id = e.current_entry_data_id ORDER BY e.id'
);
$wantCounts = $rows('SELECT m.id, d.name, (SELECT COUNT(*) FROM entries e WHERE e.model_id = m.id AND e.deleted_at IS NULL) AS live, (SELECT COUNT(*) FROM entries e WHERE e.model_id = m.id AND e.deleted_at IS NOT NULL) AS trashed FROM models m JOIN model_data d ON d.id = m.current_model_data_id ORDER BY m.id');

/** Every version: the object-format text it has to be copied as, its sorted payload, and who made it. */
$wantVersions = [];
foreach ($rows('SELECT id, entry_id, fields, creator_id, created_at FROM entry_data ORDER BY id') as $row) {
    $wasList = $isOldFormat($row['fields']);
    $wantVersions[(int) $row['id']] = [
        'entry_id'   => $row['entry_id'],
        'wasList'    => $wasList,
        'text'       => $wasList ? json_encode($asMap($row['fields']), FLAGS) : $row['fields'],
        'payload'    => $payload($wasList ? json_encode($asMap($row['fields']), FLAGS) : $row['fields']),
        'creator_id' => $row['creator_id'],
        'created_at' => $row['created_at'],
    ];
}
$listCount = count(array_filter($wantVersions, fn(array $v): bool => $v['wasList']));

// The conversion steps rewrite definitions and values after the copy, so what the database must hold is the seed run
// through the same conversions. The conversions themselves are tested on arrays. Here the run proves they were applied
// to every model, every live entry and every saved version.
require $repo . '/vendor/autoload.php';
$fieldTypes    = App\Fields\FieldTypes::core();
$convertFields = new App\Libraries\Upgrade\Convert\ModelFields($fieldTypes);
$convertValues = new App\Libraries\Upgrade\Convert\EntryValues($fieldTypes);
$modelIdsByName = [];
foreach ($wantModels as $modelId => $model) {
    $modelIdsByName[$model['name']] ??= (int) $modelId;
}
$wantDefinitions = [];
foreach (array_keys($wantModels) as $modelId) {
    $old = json_decode($wantModels[$modelId]['fields'], true);
    $wantDefinitions[$modelId] = is_array($old) ? $convertFields->convert($old, $modelIdsByName)['fields'] : [];
    $wantModels[$modelId]['fields'] = json_encode($wantDefinitions[$modelId], FLAGS);
}
foreach ($wantModelRevisions as $revisionId => $revision) {
    $old = json_decode($revision['fields'], true);
    if (is_array($old)) {
        $wantModelRevisions[$revisionId]['fields'] = json_encode($convertFields->convert($old, $modelIdsByName)['fields'], FLAGS);
    }
}
/** The text and the payload a version must hold once its values are converted. Text that did not change is kept. */
$convertVersion = function (array $want, int $modelId) use ($convertValues, $wantDefinitions, $payload): array {
    $decoded = json_decode($want['text'], true);
    if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
        return $want;
    }
    $converted = $convertValues->convert($decoded, $wantDefinitions[$modelId] ?? [])['fields'];
    if (json_encode($converted) !== json_encode($decoded)) {
        $want['text'] = App\Libraries\Upgrade\Payload::encode((object) $converted);
    }
    $want['payload'] = $payload($want['text']);

    return $want;
};
foreach ($wantVersions as $versionId => $want) {
    $entryId = $want['entry_id'];
    $modelId = (int) ($wantEntries[$entryId]['model_id'] ?? 0);
    $wantVersions[$versionId] = $convertVersion($want, $modelId);
}

// ---------------------------------------------------------------------------------------------
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
// One version in a shape that is neither format, and trashed, so it is no entry's current version. The
// upgrade must copy it as text and say so, and it still succeeds.
$pdo->exec("INSERT INTO entry_data (entry_id, fields, creator_id, created_at, updated_at, deleted_at) SELECT 1, '[\"loose\", \"list\"]', creator_id, created_at, updated_at, '2026-01-01 00:00:00' FROM entry_data WHERE id = 1");
$oddId = (int) $pdo->lastInsertId();
$wantVersions[$oddId] = ['entry_id' => $wantVersions[1]['entry_id'], 'wasList' => false, 'text' => '["loose", "list"]', 'payload' => null, 'creator_id' => $wantVersions[1]['creator_id'], 'created_at' => $wantVersions[1]['created_at']];

foreach (['entries' => ['idx_entries_model_history', 'idx_entries_deleted_at'], 'entry_data' => ['idx_entry_data_history'], 'model_data' => ['idx_model_data_history']] as $table => $names) {
    foreach ($names as $name) {
        if ($indexExists($table, $name)) {
            $pdo->exec("ALTER TABLE `$table` DROP INDEX `$name`");
        }
    }
}
if ($columnExists('entries', 'current_entry_data_id')) {
    $pdo->exec('ALTER TABLE entries DROP COLUMN current_entry_data_id');
}
if ($columnExists('models', 'current_model_data_id')) {
    $pdo->exec('ALTER TABLE models DROP COLUMN current_model_data_id');
}
$pdo->exec("DELETE FROM migrations WHERE class LIKE '%AddCurrentVersionColumns' OR class LIKE '%AddPerformanceIndexes'");

$before      = $oldTables();
$beforeAll   = $hashes($tables());

// ---------------------------------------------------------------------------------------------
// A dry run and an unanswered backup question change nothing.

[$code, $text] = $spark('--dry-run');
$check('dry run exits 0', $code === 0, $text);
$check('dry run finds the 0.1 layout', str_contains($text, 'Layout found: HyperCMS 0.1'), $text);
$counted = true;
foreach ($wantCounts as $c) {
    $counted = $counted && str_contains($text, sprintf('%d %s: %d live, %d trashed', $c['id'], $c['name'], $c['live'], $c['trashed']));
}
$check('dry run counts the live and trashed entries of every model', $counted, $text);
$check('dry run names the odd version', str_contains($text, "(ids $oddId)") && str_contains($text, 'some other shape'), $text);
$check('dry run changes nothing', $hashes($tables()) === $beforeAll && $tables() === array_keys($beforeAll));

[$code, $text] = $spark('');
$check('no answer to the backup question exits 1', $code === 1, "exit $code");
$check('no answer to the backup question changes nothing', $hashes($tables()) === $beforeAll && $tables() === array_keys($beforeAll), $text);

// ---------------------------------------------------------------------------------------------
// What a finished upgrade has to look like. Used after the first run and after the repair run.

/**
 * @param int $orphans How many StarDust entries nothing points at, left by a run that was cut short.
 */
$verify = function (string $label, int $orphans) use ($check, $byId, $rows, $pairs, $pdo, $wantModels, $wantModelRevisions, $wantEntries, $wantVersions, $payload, $oldTables, $before): void {
    $same = function (array $want, array $got): string {
        $bad = [];
        foreach ($want as $id => $row) {
            if (($got[$id] ?? null) !== $row) {
                $bad[] = $id;
            }
        }

        return $bad === [] && count($want) === count($got) ? '' : 'ids ' . implode(', ', array_slice($bad, 0, 10)) . ' of ' . count($got) . ' rows, want ' . count($want);
    };

    $gotModels = $byId('SELECT id, name, fields, `group`, icon, user_groups, current_revision_id, creator_id, editor_id, deleter_id, created_at, updated_at, deleted_at FROM hyper_models ORDER BY id');
    $detail    = $same($wantModels, $gotModels);
    $check("$label: hyper_models keep the seed's ids, definitions, authors and dates, and the current version", $detail === '', $detail);

    $detail = $same($wantModelRevisions, $byId('SELECT id, model_id, name, fields, `group`, icon, user_groups, creator_id, created_at FROM hyper_model_revisions ORDER BY id'));
    $check("$label: hyper_model_revisions hold every model version of the seed", $detail === '', $detail);

    $gotEntries = $byId('SELECT id, model_id, current_revision_id, creator_id, editor_id, deleter_id, created_at, updated_at, deleted_at FROM hyper_entries ORDER BY id');
    $detail     = $same($wantEntries, $gotEntries);
    $check("$label: hyper_entries keep the seed's ids, models, authors and dates (public ids preserved)", $detail === '', $detail);
    $check("$label: every current_revision_id is the seed's current version", array_column($gotEntries, 'current_revision_id') === array_column($wantEntries, 'current_revision_id'));
    $check("$label: every editor_id is the creator of the current version", array_column($gotEntries, 'editor_id') === array_column($wantEntries, 'editor_id'));

    // Every version came across with its id and its object-format text.
    $revisions = $byId('SELECT id, entry_id, fields, creator_id, created_at FROM hyper_entry_revisions ORDER BY id');
    $currents  = array_column($gotEntries, 'deleted_at', 'current_revision_id');
    $badText   = $badNull = $badOther = $badFormat = [];
    foreach ($wantVersions as $id => $want) {
        $got = $revisions[$id] ?? null;
        if ($got === null || $got['entry_id'] !== $want['entry_id'] || $got['creator_id'] !== $want['creator_id'] || $got['created_at'] !== $want['created_at']) {
            $badOther[] = $id;

            continue;
        }
        if (array_key_exists($id, $currents) && $currents[$id] === null) {
            // The current version of a live entry now lives in StarDust, so its text is cleared.
            if ($got['fields'] !== null) {
                $badNull[] = $id;
            }

            continue;
        }
        if ($got['fields'] !== $want['text']) {
            $badText[] = $id;
        }
        if ($want['wasList'] && ! (json_decode((string) $got['fields']) instanceof stdClass)) {
            $badFormat[] = $id;
        }
    }
    $check("$label: every entry version exists in hyper_entry_revisions with its id, entry, author and date", $badOther === [] && count($revisions) === count($wantVersions), implode(', ', $badOther));
    $check("$label: versions that are not a live current version hold the converted text", $badText === [], 'ids ' . implode(', ', $badText));
    $check("$label: versions in the old list format are now objects", $badFormat === [], 'ids ' . implode(', ', $badFormat));
    $check("$label: the current version of every live entry has NULL fields, and a trashed entry keeps its snapshot", $badNull === [] && count(array_filter($currents, fn($d) => $d !== null)) > 0, 'ids ' . implode(', ', $badNull));

    // Live entries live in StarDust now.
    $live       = array_filter($gotEntries, fn(array $e): bool => $e['deleted_at'] === null);
    $sdIds      = $pairs('SELECT id, sd_entry_id FROM hyper_entries WHERE deleted_at IS NULL ORDER BY id');
    $sdRows     = $byId('SELECT id, model_id, fields FROM sd_entry_data');
    $noId       = array_keys(array_filter($sdIds, fn($v) => $v === null));
    $check("$label: every live entry has sd_entry_id", $noId === [] && count($sdIds) === count($live), 'ids ' . implode(', ', $noId));

    $sdModelOf = $pairs('SELECT id, sd_model_id FROM hyper_models');
    $badPayload = $badModel = [];
    foreach ($sdIds as $entryId => $sdId) {
        $row = $sdRows[$sdId] ?? null;
        $rev = (int) $wantEntries[$entryId]['current_revision_id'];
        if ($row === null || $payload($row['fields']) !== ($wantVersions[$rev]['payload'] ?? 'missing')) {
            $badPayload[] = $entryId;
        }
        if ($row !== null && $row['model_id'] !== $sdModelOf[$wantEntries[$entryId]['model_id']]) {
            $badModel[] = $entryId;
        }
    }
    $check("$label: every live entry's payload in sd_entry_data equals the seed's object-format payload", $badPayload === [], 'entry ids ' . implode(', ', array_slice($badPayload, 0, 10)));
    $check("$label: every live entry sits under its own model in StarDust", $badModel === [], 'entry ids ' . implode(', ', $badModel));
    $check("$label: sd_entry_data holds one row per live entry" . ($orphans > 0 ? " and $orphans left by the cut run" : ''), count($sdRows) === count($live) + $orphans, count($sdRows) . ' rows for ' . count($live) . ' live entries');
    $check("$label: sd_entry_id is unique", count(array_unique($sdIds)) === count($sdIds));

    $trashed = array_filter($gotEntries, fn(array $e): bool => $e['deleted_at'] !== null);
    $check("$label: trashed entries have NULL sd_entry_id", $pairs('SELECT COUNT(*), COUNT(sd_entry_id) FROM hyper_entries WHERE deleted_at IS NOT NULL') === [count($trashed) => 0]);

    $names = $pairs('SELECT name, id FROM sd_stardust_models ORDER BY name');
    $want  = [];
    foreach (array_keys($wantModels) as $id) {
        $want['m' . $id] = $sdModelOf[$id];
    }
    ksort($want);
    $check("$label: sd_stardust_models holds " . implode(', ', array_keys($want)) . ' and hyper_models.sd_model_id points at them', $names === $want, json_encode($names) . ' vs ' . json_encode($want));

    $check("$label: the old tables are byte for byte as the upgrade found them", $oldTables() === $before);

    // The roles of the config are rows, with the abilities their groups had, and every model got the permissions its
    // user groups meant: every listed group reads, only the writer groups write, and no list means every role but user.
    $roleNames = $pdo->query('SELECT name FROM hyper_roles ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    $check("$label: the five roles of the config are rows, and only superadmin is locked", $roleNames === ['admin', 'beta', 'developer', 'superadmin', 'user'] && $pairs('SELECT name, locked FROM hyper_roles ORDER BY name') === ['admin' => 0, 'beta' => 0, 'developer' => 0, 'superadmin' => 1, 'user' => 0]);
    $abilities = [];
    foreach ($rows('SELECT role, ability FROM hyper_role_abilities ORDER BY role, ability') as $r) {
        $abilities[$r['role']][] = $r['ability'];
    }
    $check("$label: each role has the abilities its group had", $abilities === ['admin' => ['api.access', 'entries.browse', 'media.manage', 'tokens.manage'], 'beta' => ['media.manage'], 'developer' => ['api.access', 'entries.browse', 'media.manage', 'pages.manage', 'tokens.manage']], json_encode($abilities));

    $wantGrants = [];
    foreach ($wantModels as $id => $model) {
        $groups = json_decode((string) ($model['user_groups'] ?? '[]'), true);
        $groups = is_array($groups) ? $groups : [];
        $roles  = $groups === [] ? ['admin', 'developer', 'beta'] : array_values(array_diff(array_unique($groups), ['superadmin']));
        foreach ($roles as $role) {
            foreach (['read', 'create', 'update', 'delete', 'history'] as $action) {
                if (in_array($action, ['create', 'update', 'delete'], true) && ! in_array($role, ['admin', 'developer'], true)) {
                    continue;
                }
                $wantGrants[] = "$id|$role|$action";
            }
        }
    }
    sort($wantGrants);
    $gotGrants = array_map(fn(array $r): string => $r['model_id'] . '|' . $r['role'] . '|' . $r['action'], $rows('SELECT model_id, role, action FROM hyper_model_permissions'));
    sort($gotGrants);
    $check("$label: every model has the permissions its user groups meant", $gotGrants === $wantGrants && $wantGrants !== [], count($gotGrants) . ' rows, want ' . count($wantGrants));
};

// ---------------------------------------------------------------------------------------------
// The real run, with the odd version in it.

[$code, $text] = $spark('--yes');
echo preg_replace('/^/m', '      | ', $text) . PHP_EOL;
$check('upgrade exits 0 although one version is odd', $code === 0, "exit $code");
$check('a complete run sets the finished mark the docker entrypoint waits for', (int) $pdo->query("SELECT COUNT(*) FROM hyper_counters WHERE name = 'upgrade_finished'")->fetchColumn() === 1);
$check('upgrade warns and names the odd version', str_contains($text, 'hold a list in some other shape and were copied as text. Version ids: ' . $oddId), $text);
$check('upgrade copied every model, entry and version', str_contains($text, 'Copied ' . count($wantModels) . ' models and ' . count($wantModelRevisions) . ' model versions.') && str_contains($text, 'Copied ' . count($wantEntries) . ' entries and ' . count($wantVersions) . ' entry versions.'), $text);
$liveCount = count(array_filter($wantEntries, fn(array $e): bool => $e['deleted_at'] === null));
$check('upgrade wrote every live entry to StarDust and read them all back', str_contains($text, "Wrote $liveCount entries to StarDust.") && str_contains($text, 'The upgrade is complete.'), $text);
$check('the seed had entries in the old list format to convert', $listCount >= 5, "$listCount versions");

$verify('first run', 0);
$odd = $rows("SELECT fields FROM hyper_entry_revisions WHERE id = $oddId");
$check('the odd version is copied as it was, as text', ($odd[0]['fields'] ?? null) === '["loose", "list"]');

// The conversion steps ran, and the shapes of the old form came out as the new ones.
$check('the schema version is the latest after a complete run', (int) $pdo->query("SELECT value FROM hyper_counters WHERE name = 'schema_version'")->fetchColumn() === App\Libraries\Upgrade\Steps\Steps::LATEST);
$labels = $pairs('SELECT name, label_field FROM hyper_models ORDER BY id');
$check('every model got the label field its fields suggest', ($labels['Article'] ?? null) === 'title' && ($labels['Product'] ?? null) === 'sku' && ($labels['Shapes'] ?? null) === 'title' && ($labels['Pages'] ?? null) === 'path' && ($labels['Guarded'] ?? null) === 'note', json_encode($labels));
$shapeId  = (int) $pdo->query("SELECT id FROM hyper_models WHERE name = 'Shapes'")->fetchColumn();
$shapeDef = array_column(json_decode((string) $pdo->query("SELECT fields FROM hyper_models WHERE id = $shapeId")->fetchColumn(), true), null, 'id');
$check('the old field shapes became version 2 types', array_map(fn($f) => $f['type'], $shapeDef) === ['title' => 'text', 'mail' => 'text', 'body' => 'richtext', 'snippet' => 'code', 'logo' => 'file', 'photos' => 'file', 'rating' => 'number', 'count' => 'number', 'price' => 'number', 'active' => 'boolean', 'size' => 'choice', 'tags' => 'choice', 'kind' => 'choice', 'colour' => 'color', 'starts' => 'datetime', 'secret' => 'hidden'], json_encode(array_map(fn($f) => $f['type'], $shapeDef)));
$queryDef = array_column(json_decode((string) $pdo->query("SELECT fields FROM hyper_models WHERE name = 'Queries'")->fetchColumn(), true), 'type', 'id');
$check('data queries became model, relation and hook fields, and the rest became text', $queryDef === ['title' => 'text', 'model' => 'model', 'shape' => 'relation', 'hook' => 'hook', 'whole' => 'text', 'owner' => 'text'], json_encode($queryDef));
$shapeEntry = $pdo->query("SELECT d.fields FROM hyper_entries e JOIN sd_entry_data d ON d.id = e.sd_entry_id WHERE e.model_id = $shapeId ORDER BY e.id LIMIT 1")->fetchColumn();
$shapeValues = json_decode((string) $shapeEntry, true);
$check('the values of a Shapes entry took the new shape', is_array($shapeValues) && $shapeValues['rating'] === 7 && $shapeValues['count'] === 3 && $shapeValues['price'] === 12.5 && $shapeValues['active'] === true && $shapeValues['tags'] === ['b'] && $shapeValues['starts'] === '2026-03-01 08:30:00' && $shapeValues['colour'] === '#aa00ff' && $shapeValues['photos'] === ['uploads/photo-one.png', 'uploads/photo-two.png'] && is_string($shapeValues['logo']), (string) $shapeEntry);
$oddValues = json_decode((string) $pdo->query("SELECT d.fields FROM hyper_entries e JOIN sd_entry_data d ON d.id = e.sd_entry_id WHERE e.model_id = $shapeId ORDER BY e.id LIMIT 1 OFFSET 2")->fetchColumn(), true);
$check('a value that fits no rule is kept as it was', is_array($oddValues) && $oddValues['count'] === 'many' && $oddValues['active'] === 'maybe' && $oddValues['starts'] === 'soon', json_encode($oddValues));

// ---------------------------------------------------------------------------------------------
// A second run finds nothing to do, and no row of any new table changes.

$settled = $newTables();
$settledOld = $oldTables();
[$code, $text] = $spark('--yes');
$check('a second run exits 0', $code === 0, $text);
$after = $newTables();
$moved = array_keys(array_filter($after, fn(string $h, string $t): bool => ($settled[$t] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
$check('a second run changes no row in any hyper_ or sd_ table', $moved === [] && array_keys($after) === array_keys($settled), implode(', ', $moved));
$check('a second run leaves the old tables alone', $oldTables() === $settledOld);

// ---------------------------------------------------------------------------------------------
// A run that was cut short. Rebuild four states a crash can leave and run again.
//   entries 3, 4 and 24: copied to nothing yet (no hyper_entries row, no StarDust entry, text still in the revision)
//   entry 7: written to StarDust, but the id was never recorded (an orphan in StarDust, text still in the revision)
//   model 3: registered in StarDust, but sd_model_id was never recorded

$restore = $pdo->prepare('UPDATE hyper_entry_revisions SET fields = ? WHERE id = ?');
$gone    = [3, 4, 24];
foreach ([...$gone, 7] as $entryId) {
    $entry = $pdo->query("SELECT current_revision_id, sd_entry_id FROM hyper_entries WHERE id = $entryId")->fetch(PDO::FETCH_ASSOC);
    $restore->execute([$wantVersions[(int) $entry['current_revision_id']]['text'], $entry['current_revision_id']]);
    if ($entryId !== 7 && $entry['sd_entry_id'] !== null) {
        $pdo->exec('DELETE FROM sd_entry_data WHERE id = ' . (int) $entry['sd_entry_id']);
    }
}
$pdo->exec('DELETE FROM hyper_entries WHERE id IN (' . implode(',', $gone) . ')');
$pdo->exec('UPDATE hyper_entries SET sd_entry_id = NULL, payload_bytes = 0 WHERE id = 7');
$pdo->exec('UPDATE hyper_models SET sd_model_id = NULL WHERE id = 3');
$check('the cut state has fewer hyper_entries and unrecorded StarDust ids', (int) $pdo->query('SELECT COUNT(*) FROM hyper_entries')->fetchColumn() === count($wantEntries) - count($gone));

[$code, $text] = $spark('--yes');
echo preg_replace('/^/m', '      | ', $text) . PHP_EOL;
$check('a run on half filled tables exits 0', $code === 0, "exit $code");
$check('a run on half filled tables adds the missing entries', str_contains($text, 'Copied ' . count($gone) . ' entries and 0 entry versions.'), $text);
$verify('repair run', 1);

echo ($failed === 0 ? 'upgrade test passed' : "upgrade test: $failed checks failed") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
