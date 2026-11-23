<?php
declare(strict_types=1);

// Hostile sort and find values against the DataTables endpoints, on a throwaway server.
// A request is EXPOSED when its text reaches SQL and makes the database complain about it.
// The probes sign in first, so they test the queries and are not stopped at the login check.
// usage: php probe.php <baseUrl>
$base = rtrim($argv[1] ?? 'http://localhost:8099', '/') . '/';
$envFile = $argv[2] ?? '';

function post(string $url, array $form, string $token = ''): array
{
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true, 'timeout' => 60,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json" . ($token !== '' ? "\r\nAuthorization: Bearer $token" : ''),
        'content' => http_build_query($form),
    ]]);
    $body = (string) @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $l) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $l, $m)) {
            $status = (int) $m[1];
        }
    }
    $j = json_decode($body, true);
    return [$status, is_array($j) ? (string) ($j['message'] ?? '') : ''];
}

$sort = fn(string $col, array $extra = []) => array_merge(['draw' => 1, 'start' => 0, 'length' => 3, 'order' => [['column' => 0, 'dir' => 'asc']], 'columns' => [['data' => $col]]], $extra);
$bomb = '(SELECT 1 FROM zz_no_such_table)';

$probes = [
    'models, sort subquery'            => ['api/v1/models', $sort($bomb)],
    'models, sort lone quote'          => ['api/v1/models', $sort("zz'")],
    'model-data, sort subquery'        => ['api/v1/model-data', $sort($bomb, ['id' => 1])],
    'entries, sort subquery'           => ['api/v1/entries', $sort($bomb)],
    'model, sort lone quote'           => ['api/v1/model', $sort("zz'", ['id' => 1])],
    'entry-data, sort lone quote'      => ['api/v1/entry-data', $sort("zz'", ['id' => 1])],
    'entry-data, find field quote'     => ['api/v1/entry-data', ['draw' => 1, 'id' => 1, 'find' => ['field' => "zz'", 'value' => 'a']]],
    'entry-data, find value quote'     => ['api/v1/entry-data', ['draw' => 1, 'id' => 1, 'find' => ['field' => 'title', 'value' => "a'"]]],
    'entry-data, find normal (legit)'  => ['api/v1/entry-data', ['draw' => 1, 'id' => 1, 'find' => ['field' => 'title', 'value' => 'edited']]],
];

$login = (string) @file_get_contents($base . 'api/v1/auth/login', false, stream_context_create(['http' => [
    'method' => 'POST', 'ignore_errors' => true, 'timeout' => 60,
    'header' => "Content-Type: application/json\r\nAccept: application/json",
    'content' => json_encode(['email' => 'capture_admin@example.test', 'password' => 'Capture-Admin-2026']),
]]));
$token = json_decode($login, true)['access_token'] ?? '';
if ($token === '') {
    echo "Could not sign in as capture_admin, so nothing was probed.\n";
    exit(1);
}

$exposed = 0;
$blocked = 0;
foreach ($probes as $label => [$path, $form]) {
    [$status, $msg] = post($base . $path, $form, $token);
    $bad = $status === 500 && preg_match('/SQL syntax|zz_no_such_table/i', $msg) === 1;
    $exposed += $bad ? 1 : 0;
    // A refused request never reached the query, so it says nothing about the query.
    $refused = $status === 401 || $status === 403;
    $blocked += $refused ? 1 : 0;
    echo str_pad($label, 36) . str_pad((string) $status, 5) . ($bad ? 'EXPOSED  ' . substr($msg, 0, 60) : ($refused ? 'REFUSED, not tested' : 'safe')) . PHP_EOL;
}
echo "$exposed of " . count($probes) . " probes reach SQL\n";
if ($blocked > 0) {
    echo "$blocked probes were refused before the query ran\n";
}

// File access and history access. A probe is EXPOSED when the request got what it should not have.
// These sign in through the real form, so they run against a throwaway database and a throwaway checkout.
$public = realpath(__DIR__ . '/../../public');

/** Send one request with a cookie jar and return [status, body, location]. */
function web(string $base, array &$jar, string $method, string $path, ?string $body = null, array $headers = []): array
{
    $h = $headers;
    if ($jar) {
        $h[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($jar), $jar));
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'timeout' => 60, 'follow_location' => 0, 'max_redirects' => 0,
        'header' => implode("\r\n", $h), 'content' => $body ?? '',
    ]]);
    $out = (string) @file_get_contents($base . $path, false, $ctx);
    $status = 0;
    $loc = null;
    foreach ($http_response_header ?? [] as $l) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $l, $m)) {
            $status = (int) $m[1];
        } elseif (stripos($l, 'Location:') === 0) {
            $loc = trim(substr($l, 9));
        } elseif (stripos($l, 'Set-Cookie:') === 0) {
            [$kv] = explode(';', trim(substr($l, 11)), 2);
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, '');
            if ($v === '' || $v === 'deleted') {
                unset($jar[$k]);
            } else {
                $jar[$k] = $v;
            }
        }
    }
    return [$status, $out, $loc];
}

/** Sign in through the form. Returns the cookie jar and the CSRF token. */
function signIn(string $base, string $email, string $password): array
{
    $jar = [];
    [, $page] = web($base, $jar, 'GET', 'auth/login');
    $token = preg_match('/name="hyper_csrf_token"\s+value="([a-f0-9]+)"/', $page, $m) ? $m[1] : '';
    web($base, $jar, 'POST', 'auth/login', http_build_query(['email' => $email, 'password' => $password, 'hyper_csrf_token' => $token]), ['Content-Type: application/x-www-form-urlencoded']);
    // Any admin page carries the token of the signed-in session in its script config.
    [, $page] = web($base, $jar, 'GET', 'admin/dashboard');
    $token = preg_match('/"csrfHash":"([a-f0-9]+)"/', $page, $m) ? $m[1] : $token;
    return [$jar, $token];
}

$say = function (string $label, int $status, bool $bad, string $note = '') use (&$exposed): void {
    $exposed += $bad ? 1 : 0;
    echo str_pad($label, 44) . str_pad((string) $status, 5) . ($bad ? 'EXPOSED  ' . $note : 'safe') . PHP_EOL;
};

// The public file route, with no sign-in. It must answer 404 for anything outside the public folder or not servable.
$anon = [];
foreach (['.env' => '.env', 'app/Config/App.php' => 'app/Config/App.php', 'public/index.php' => 'public/index.php', 'public/../.env' => 'public/../.env'] as $label => $path) {
    [$status, $body] = web($base, $anon, 'GET', 'public/file-server/serve/' . bin2hex($path));
    $say("file-server, $label", $status, $status === 200, strlen($body) . ' bytes');
}
[$status] = web($base, $anon, 'GET', 'public/file-server/serve/' . bin2hex('robots.txt'));
$say('file-server, robots.txt (legit)', $status, $status !== 200, 'a public file was refused');

// File manager writes, as the superadmin. Everything happens in one folder that is removed at the end.
[$jar, $token] = signIn($base, 'capture_admin@example.test', 'Capture-Admin-2026');
$json = ['Content-Type: application/json', 'Accept: application/json', 'X-Requested-With: XMLHttpRequest', 'X-HYPER-CSRF-TOKEN: ' . $token];
$fm = fn(string $path, array $data) => web($base, $jar, 'POST', 'admin/api/file-manager/' . $path, json_encode($data), $json);
$upload = function (string $dir, string $name, string $content) use ($base, &$jar, $token): array {
    $b = 'probe' . bin2hex(random_bytes(4));
    $body = "--$b\r\nContent-Disposition: form-data; name=\"hyper_csrf_token\"\r\n\r\n$token\r\n"
        . "--$b\r\nContent-Disposition: form-data; name=\"path\"\r\n\r\n$dir\r\n"
        . "--$b\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$name\"\r\nContent-Type: application/octet-stream\r\n\r\n$content\r\n--$b--\r\n";
    return web($base, $jar, 'POST', 'admin/api/file-manager/upload', $body, ["Content-Type: multipart/form-data; boundary=$b", 'Accept: application/json', 'X-HYPER-CSRF-TOKEN: ' . $token]);
};

$dir = 'probe_tmp_' . bin2hex(random_bytes(3));
$fm('create-folder', ['path' => '', 'folderName' => $dir]);
// The file manager root is public/, or a folder inside it when .env sets one, so find where the folder landed.
$root = is_dir("$public/$dir") ? $public : (is_dir("$public/uploads/$dir") ? "$public/uploads" : null);
if ($root === null) {
    echo "The file manager made no folder, so its probes could not run
";
    exit(1);
}
$mk = fn(string $name) => is_file("$root/$dir/$name");

foreach (['x.php', 'x.php.jpg', 'x.phtml', '.htaccess'] as $name) {
    $upload($dir, $name, '<?php echo 1;');
    $say("upload, $name", 0, $mk($name), 'the file is on disk');
}
$upload($dir, 'ok.txt', 'plain');
$say('upload, ok.txt (legit)', 0, !$mk('ok.txt'), 'a plain file was refused');

$fm('rename', ['oldPath' => "$dir/ok.txt", 'newName' => '../escaped.txt']);
$say('rename, ok.txt to ../escaped.txt', 0, is_file("$root/escaped.txt"), 'the file left its folder');
$fm('rename', ['oldPath' => "$dir/ok.txt", 'newName' => 'moved.php']);
$say('rename, ok.txt to moved.php', 0, $mk('moved.php'), 'the file is on disk');
@unlink("$root/escaped.txt");
$fm('create-file', ['path' => $dir, 'fileName' => 'y.php']);
$say('create-file, y.php', 0, $mk('y.php'), 'the file is on disk');
$fm('save-file', ['path' => bin2hex("$dir/ok.txt"), 'content' => 'edited']);
$say('save-file, ok.txt (legit)', 0, !is_file("$root/$dir/ok.txt") || file_get_contents("$root/$dir/ok.txt") !== 'edited', 'a plain file could not be saved');
foreach (['../.env', '../../.env'] as $up) {
    $fm('bulk-action', ['action' => 'copy', 'files' => [$up], 'destination' => $dir]);
}
$say('bulk-action, copy ../.env', 0, $mk('.env'), 'the project .env was copied into public');
// A decoy outside public stands in for the project files, so a delete that escapes costs nothing.
$decoy = realpath(__DIR__ . '/../../writable') . '/probe_decoy.txt';
file_put_contents($decoy, 'decoy');
foreach (['../writable/probe_decoy.txt', '../../writable/probe_decoy.txt'] as $up) {
    $fm('delete-files', ['files' => [$up]]);
}
$say('delete-files, ../writable/probe_decoy.txt', 0, !is_file($decoy), 'a file outside public was deleted');
@unlink($decoy);

$zipPath = "$root/$dir/z.zip";
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $zip->addFromString('inside.php', '<?php echo 1;');
        $zip->addFromString('fine.txt', 'fine');
        $zip->close();
        $fm('extract', ['path' => "$dir/z.zip"]);
        $say('extract, an archive holding inside.php', 0, $mk('inside.php') || $mk('fine.txt'), 'the archive was unpacked');
    } else {
        echo "extract probe skipped, the test archive could not be written
";
    }
}

$fm('delete-files', ['files' => [$dir]]);
if (is_dir("$root/$dir")) {
    foreach (glob("$root/$dir/{,.}*", GLOB_BRACE) ?: [] as $f) {
        is_file($f) && @unlink($f);
    }
    @rmdir("$root/$dir");
}

// The history of a model is for superadmins, and a restore rewrites the model. Entry histories follow the model of the entry.
// An account in the `user` group may open none of them, and a superadmin may open the history of any live model.
[$ujar, $utoken] = signIn($base, 'capture_user@example.test', 'Capture-User-2026');
$form = ['Content-Type: application/x-www-form-urlencoded', 'X-HYPER-CSRF-TOKEN' . ': ' . $utoken];
foreach (['admin/model-data/5' => 'model history page, model 5', 'admin/model-data/1' => 'model history page, model 1'] as $path => $label) {
    [$status] = web($base, $ujar, 'GET', $path);
    $say("history, $label, user account", $status, $status === 200, 'a model history was opened without the group');
}
[$status] = web($base, $ujar, 'GET', 'admin/model/5/43/history');
$say('history, entry history page, user account', $status, $status === 200, 'a restricted entry history was opened');
foreach (['admin/model-data/1/clear' => 'clear model history', 'admin/model-data/1/versions/1/restore' => 'restore a model version', 'admin/model/5/43/history/clear' => 'clear entry history'] as $path => $label) {
    [$status, , $loc] = web($base, $ujar, 'POST', $path, http_build_query(['hyper_csrf_token' => $utoken]), $form);
    // Allowed means the action ran, which answers 200 or 204, or redirects back with a success flash.
    $say("history, $label, user account", $status, $status === 200 || $status === 204 || ($status >= 300 && $status < 400 && str_contains((string) $loc, 'history')), 'a history was changed without the right');
}
[$ajar] = signIn($base, 'capture_admin@example.test', 'Capture-Admin-2026');
[$status] = web($base, $ajar, 'GET', 'admin/model-data/1');
$say('history, model 1 page, superadmin (legit)', $status, $status !== 200, 'an open model history was refused');

// Every role against the pages and the writes of the admin. The accounts are a superadmin, a developer and a member of
// `user`. Each row says whether that role may open the page, and anything that does not match is reported: a page opened
// without the right is exposed, and a page refused to a role that has the right is a block that stops the work.
$roles = [
    'superadmin' => ['capture_admin@example.test', 'Capture-Admin-2026'],
    'developer'  => ['capture_dev@example.test', 'Capture-Dev-2026'],
    'user'       => ['capture_user@example.test', 'Capture-User-2026'],
];
$matrix = [
    // page                              superadmin developer user
    'admin/dashboard'                 => [true, true, true],
    'admin/settings'                  => [true, true, true],
    'admin/models'                    => [true, false, false],
    'admin/models/new'                => [true, false, false],
    'admin/models/1/edit'             => [true, false, false],
    'admin/model-data/1'              => [true, false, false],
    'admin/settings/models'           => [true, false, false],
    'admin/users'                     => [true, false, false],
    'admin/entries'                   => [true, true, false],
    'admin/file-manager'              => [true, true, false],
    'admin/model/1'                   => [true, true, false],
    'admin/model/1/new'               => [true, true, false],
    'admin/model/1/1/edit'            => [true, true, false],
    'admin/model/1/1/history'         => [true, true, false],
    'admin/model/5'                   => [true, true, false],
    'admin/model/5/new'               => [true, true, false],
    'admin/settings/paging-system'    => [true, true, false],
];
$posts = [
    // write                                            superadmin developer user
    'admin/models/check'                             => [true, false, false],
    'admin/model/1/trash'                            => [true, true, false],
    'admin/models/delete'                            => [true, false, false],
];
$form = fn(string $token): array => ['Content-Type: application/x-www-form-urlencoded', 'X-HYPER-CSRF-TOKEN' . ': ' . $token];
$names = array_keys($roles);
foreach ($roles as $role => [$email, $password]) {
    [$rjar, $rtoken] = signIn($base, $email, $password);
    $column = array_search($role, $names, true);
    foreach ($matrix as $path => $allowed) {
        [$status] = web($base, $rjar, 'GET', $path);
        $opened = $status === 200;
        $bad    = $opened !== $allowed[$column];
        $say("role $role, GET $path", $status, $bad, $opened ? 'opened without the right' : 'refused although the role has the right');
    }
    foreach ($posts as $path => $allowed) {
        [$status, , $loc] = web($base, $rjar, 'POST', $path, http_build_query(['hyper_csrf_token' => $rtoken, 'ids' => [999999]]), $form($rtoken));
        // A refusal is a 403 or a redirect to the dashboard. Anything else means the request reached the controller.
        $refused = $status === 403 || ($status >= 300 && $status < 400 && str_contains((string) $loc, 'dashboard'));
        $bad     = $refused === $allowed[$column];
        $say("role $role, POST $path", $status, $bad, $refused ? 'refused although the role has the right' : 'reached the controller without the right');
    }
}

// The roles and abilities screens, and the ways a person with only part of the power could reach more. The probe makes
// two roles and two accounts, tries to escalate with each, and removes everything again.
if ($envFile !== '') {
    require_once __DIR__ . '/lib.php';
    $pdo = connect(dbSettings($envFile), true);
    [$sa, $satoken] = signIn($base, 'capture_admin@example.test', 'Capture-Admin-2026');
    $sform = ['Content-Type: application/x-www-form-urlencoded', 'X-HYPER-CSRF-TOKEN' . ': ' . $satoken];
    $act   = function (array &$jar, string $token, string $path, array $fields) use ($base): array {
        return web($base, $jar, 'POST', $path, http_build_query($fields + ['hyper_csrf_token' => $token]), ['Content-Type: application/x-www-form-urlencoded', 'X-HYPER-CSRF-TOKEN' . ': ' . $token]);
    };
    $rolesOf = fn(string $user): array => $pdo->query("SELECT `group` FROM auth_groups_users g JOIN users u ON u.id = g.user_id WHERE u.username = " . $pdo->quote($user))->fetchAll(PDO::FETCH_COLUMN);

    foreach (['admin/roles', 'admin/roles/new', 'admin/roles/admin/edit', 'admin/roles/superadmin/edit', 'admin/abilities'] as $page) {
        [$status] = web($base, $sa, 'GET', $page);
        $say("roles, superadmin opens $page", $status, $status !== 200, 'a screen of the superadmin was refused');
    }

    // Roles: make, refuse a bad or repeated name, refuse to delete a locked role or one with people in it.
    [$status] = $act($sa, $satoken, 'admin/roles', ['name' => 'probe_users', 'label' => 'Probe users', 'copy' => '', 'abilities' => ['users.manage']]);
    $say('roles, make a role', $status, $status !== 303, 'a valid role was not made');
    [$status] = $act($sa, $satoken, 'admin/roles', ['name' => 'probe_roles', 'label' => 'Probe roles', 'abilities' => ['roles.manage']]);
    [$status] = $act($sa, $satoken, 'admin/roles', ['name' => 'probe_users', 'label' => 'Again']);
    $say('roles, the same name twice', $status, $status === 303, 'a repeated name was accepted');
    [$status] = $act($sa, $satoken, 'admin/roles', ['name' => 'Bad Name!', 'label' => 'Bad']);
    $say('roles, a name with spaces and capitals', $status, $status === 303, 'a bad name was accepted');
    $act($sa, $satoken, 'admin/roles/superadmin/delete', []);
    $say('roles, delete the locked role', 200, (int) $pdo->query("SELECT COUNT(*) FROM hyper_roles WHERE name = 'superadmin'")->fetchColumn() !== 1, 'the locked role was deleted');
    $act($sa, $satoken, 'admin/roles/developer/delete', []);
    $say('roles, delete a role that has people', 200, (int) $pdo->query("SELECT COUNT(*) FROM hyper_roles WHERE name = 'developer'")->fetchColumn() !== 1, 'a role with people was deleted');

    // Abilities: add one by name, refuse a bad or repeated name, refuse to delete an ability of core.
    [$status] = $act($sa, $satoken, 'admin/abilities', ['name' => 'probe.thing', 'label' => 'Probe thing']);
    $say('abilities, add one by name', $status, $status !== 303, 'a valid ability was not added');
    [$status] = $act($sa, $satoken, 'admin/abilities', ['name' => 'probe.thing', 'label' => 'Again']);
    $say('abilities, the same name twice', $status, $status === 303, 'a repeated name was accepted');
    [$status] = $act($sa, $satoken, 'admin/abilities', ['name' => 'single', 'label' => 'One word']);
    $say('abilities, a name of one word', $status, $status === 303, 'a bad name was accepted');
    [$status] = $act($sa, $satoken, 'admin/abilities', ['name' => 'entries.read', 'label' => 'Clash']);
    $say('abilities, a name of a model action', $status, $status === 303, 'a name that clashes with a model action was accepted');
    $act($sa, $satoken, 'admin/abilities/media.manage/delete', []);
    $say('abilities, delete an ability of core', 200, (int) $pdo->query("SELECT COUNT(*) FROM hyper_role_abilities WHERE ability = 'media.manage'")->fetchColumn() === 0, 'an ability of core was deleted');

    // Two accounts that each have part of the power.
    foreach (['probe_manager' => 'probe_users', 'probe_editor' => 'probe_roles'] as $user => $role) {
        $act($sa, $satoken, 'admin/users/save', ['username' => $user, 'email' => $user . '@example.test', 'password' => 'Probe-Passw0rd-2026!', 'groups' => [$role]]);
    }

    // A person who may manage users must not reach the superadmin role, or an account that has it.
    [$mj, $mtoken] = signIn($base, 'probe_manager@example.test', 'Probe-Passw0rd-2026!');
    $act($mj, $mtoken, 'admin/users/save', ['username' => 'probe_manager', 'email' => 'probe_manager@example.test', 'id' => (string) $pdo->query("SELECT id FROM users WHERE username = 'probe_manager'")->fetchColumn(), 'groups' => ['probe_users', 'superadmin']]);
    $say('users, a manager makes themselves superadmin', 200, in_array('superadmin', $rolesOf('probe_manager'), true), 'the person reached the superadmin role');
    $act($mj, $mtoken, 'admin/users/save', ['username' => 'probe_friend', 'email' => 'probe_friend@example.test', 'password' => 'Probe-Passw0rd-2026!', 'groups' => ['superadmin']]);
    $say('users, a manager makes a superadmin account', 200, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'probe_friend'")->fetchColumn() > 0 && in_array('superadmin', $rolesOf('probe_friend'), true), 'a superadmin account was made');
    $act($mj, $mtoken, 'admin/users/save', ['username' => 'capture_admin', 'email' => 'capture_admin@example.test', 'password' => 'Hijacked-Passw0rd-2026!', 'id' => (string) $pdo->query("SELECT id FROM users WHERE username = 'capture_admin'")->fetchColumn(), 'groups' => ['superadmin']]);
    [$cj] = signIn($base, 'capture_admin@example.test', 'Hijacked-Passw0rd-2026!');
    [$status] = web($base, $cj, 'GET', 'admin/models');
    $say('users, a manager resets a superadmin password', $status, $status === 200, 'the password of a superadmin was changed');
    $act($mj, $mtoken, 'admin/users/' . $pdo->query("SELECT id FROM users WHERE username = 'capture_admin'")->fetchColumn() . '/delete', []);
    $say('users, a manager deletes a superadmin', 200, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'capture_admin'")->fetchColumn() === 0, 'a superadmin account was deleted');

    // A person who may manage roles can hand out only what they have.
    [$ej, $etoken] = signIn($base, 'probe_editor@example.test', 'Probe-Passw0rd-2026!');
    $act($ej, $etoken, 'admin/roles/probe_roles', ['label' => 'Probe roles', 'abilities' => ['roles.manage', 'users.manage', 'models.manage']]);
    $held = $pdo->query("SELECT ability FROM hyper_role_abilities WHERE role = 'probe_roles' ORDER BY ability")->fetchAll(PDO::FETCH_COLUMN);
    $say('roles, an editor gives their own role more power', 200, $held !== ['roles.manage'], 'the role now has ' . implode(', ', $held));
    $act($ej, $etoken, 'admin/roles/superadmin', ['label' => 'Everyone', 'abilities' => []]);
    $say('roles, an editor renames the superadmin role', 200, (string) $pdo->query("SELECT label FROM hyper_roles WHERE name = 'superadmin'")->fetchColumn() === 'Everyone', 'the superadmin role was changed');
    $act($ej, $etoken, 'admin/roles', ['name' => 'probe_copy', 'label' => 'Copy', 'copy' => 'developer']);
    $say('roles, an editor copies a role with more power', 200, (int) $pdo->query("SELECT COUNT(*) FROM hyper_roles WHERE name = 'probe_copy'")->fetchColumn() > 0 && (int) $pdo->query("SELECT COUNT(*) FROM hyper_role_abilities WHERE role = 'probe_copy'")->fetchColumn() > 0, 'a role with abilities the editor lacks was copied');

    // An ability that a higher role has cannot be deleted by someone who may not change that role.
    $pdo->exec("INSERT INTO hyper_abilities (name, label, created_at) VALUES ('probe.held', 'Held', NOW())");
    $pdo->exec("INSERT INTO hyper_role_abilities (role, ability) VALUES ('developer', 'probe.held')");
    $act($ej, $etoken, 'admin/abilities/probe.held/delete', []);
    $say('abilities, an editor deletes one that a higher role has', 200, (int) $pdo->query("SELECT COUNT(*) FROM hyper_abilities WHERE name = 'probe.held'")->fetchColumn() === 0, 'an ability held by a higher role was deleted');

    // Clean up, in the order that the guards allow.
    $pdo->exec("DELETE FROM auth_groups_users WHERE user_id IN (SELECT id FROM users WHERE username IN ('probe_manager', 'probe_editor', 'probe_friend'))");
    $pdo->exec("DELETE FROM auth_identities WHERE user_id IN (SELECT id FROM users WHERE username IN ('probe_manager', 'probe_editor', 'probe_friend'))");
    $pdo->exec("DELETE FROM users WHERE username IN ('probe_manager', 'probe_editor', 'probe_friend')");
    $pdo->exec("DELETE FROM hyper_role_abilities WHERE role LIKE 'probe\\_%'");
    $pdo->exec("DELETE FROM hyper_roles WHERE name LIKE 'probe\\_%'");
    $pdo->exec("DELETE FROM hyper_abilities WHERE name LIKE 'probe.%'");
    $pdo->exec("UPDATE hyper_roles SET label = 'Super Admin' WHERE name = 'superadmin'");
}

echo "$exposed probes were exposed in all\n";
exit($exposed === 0 && $blocked === 0 ? 0 : 1);
