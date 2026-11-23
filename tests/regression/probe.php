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

/**
 * Sign in through the form. Returns the cookie jar and the CSRF token.
 *
 * The login pages allow ten requests a minute from one address, and a probe that signs in many times is turned away
 * without a sign. A sign in that did not reach the admin therefore waits and tries again, and gives up with a message
 * rather than let an attack run from a session that does not exist.
 */
function signIn(string $base, string $email, string $password, bool $mustSucceed = true): array
{
    for ($try = 0; $try < ($mustSucceed ? 6 : 1); $try++) {
        $jar = [];
        [$status, $page] = web($base, $jar, 'GET', 'auth/login');
        $token = preg_match('/name="hyper_csrf_token"\s+value="([a-f0-9]+)"/', $page, $m) ? $m[1] : '';
        if ($status === 200 && $token !== '') {
            web($base, $jar, 'POST', 'auth/login', http_build_query(['email' => $email, 'password' => $password, 'hyper_csrf_token' => $token]), ['Content-Type: application/x-www-form-urlencoded']);
            // Any admin page carries the token of the signed-in session in its script config.
            [$status, $page] = web($base, $jar, 'GET', 'admin/dashboard');
            if ($status === 200) {
                return [$jar, preg_match('/"csrfHash":"([a-f0-9]+)"/', $page, $m) ? $m[1] : $token];
            }
        }
        if ($mustSucceed) {
            sleep(15);
        }
    }

    if (! $mustSucceed) {
        return [$jar ?? [], ''];
    }

    fwrite(STDERR, "Could not sign in as $email, so nothing can be probed from that account.
");
    exit(1);
}

$say = function (string $label, int $status, bool $bad, string $note = '') use (&$exposed): void {
    $exposed += $bad ? 1 : 0;
    echo str_pad($label, 44) . str_pad((string) $status, 5) . ($bad ? 'EXPOSED  ' . $note : 'safe') . PHP_EOL;
};

// The old public file route is gone, so every path, a real file or the project's secrets, answers 404 with no sign-in.
$anon = [];
foreach (['.env' => '.env', 'app/Config/App.php' => 'app/Config/App.php', 'public/index.php' => 'public/index.php', 'public/../.env' => 'public/../.env', 'robots.txt' => 'robots.txt'] as $label => $path) {
    [$status, $body] = web($base, $anon, 'GET', 'public/file-server/serve/' . bin2hex($path));
    $say("file-server, $label", $status, $status === 200, strlen($body) . ' bytes');
}

// The media library, as the superadmin. A file is kept outside the public folder, and a name that a server could run, or a
// folder that leaves the library, must be refused. Everything happens in one folder that is removed at the end.
[$jar, $token] = signIn($base, 'capture_admin@example.test', 'Capture-Admin-2026');
$libraryRoot = realpath(__DIR__ . '/../../writable') . DIRECTORY_SEPARATOR . 'uploads';
$post = fn(string $path, array $fields) => web($base, $jar, 'POST', $path, http_build_query($fields + ['hyper_csrf_token' => $token]), ['Content-Type: application/x-www-form-urlencoded', 'Accept: text/html', 'X-Requested-With: XMLHttpRequest', 'X-HYPER-CSRF-TOKEN: ' . $token]);
$stored = [];
$upload = function (string $folder, string $name, string $content) use ($base, &$jar, $token, &$stored): array {
    $b    = 'probe' . bin2hex(random_bytes(4));
    $body = "--$b\r\nContent-Disposition: form-data; name=\"hyper_csrf_token\"\r\n\r\n$token\r\n"
        . "--$b\r\nContent-Disposition: form-data; name=\"folder\"\r\n\r\n$folder\r\n"
        . "--$b\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"$name\"\r\nContent-Type: application/octet-stream\r\n\r\n$content\r\n--$b--\r\n";
    [$status, $answer] = web($base, $jar, 'POST', 'admin/media/upload', $body, ["Content-Type: multipart/form-data; boundary=$b", 'Accept: application/json', 'X-HYPER-CSRF-TOKEN: ' . $token]);
    $json = json_decode($answer, true);
    foreach (is_array($json) ? ($json['files'] ?? []) : [] as $file) {
        $stored[] = (int) $file['id'];
    }

    return is_array($json) ? $json : ['files' => [], 'problems' => ['no answer, status ' . $status]];
};
$headersOf = function (string $path) use ($base): array {
    @file_get_contents($base . $path, false, stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'follow_location' => 0]]));
    $found = [];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $found['status'] = $m[1];
        } elseif (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $found[strtolower(trim($k))] = trim($v);
        }
    }

    return $found;
};

$dir = 'probe_tmp_' . bin2hex(random_bytes(3));
$post('admin/media/folders', ['mode' => 'library', 'folder' => '', 'new_folder' => $dir]);
if (! is_dir("$libraryRoot/$dir")) {
    echo "The media library made no folder, so its probes could not run
";
    exit(1);
}
$mk = fn(string $name) => is_file("$libraryRoot/$dir/$name");

foreach (['x.php', 'x.php.jpg', 'x.phtml', '.htaccess', 'x.PHP', 'x.phar'] as $name) {
    $upload($dir, $name, '<?php echo 1;');
    $say("upload, $name", 0, $mk($name), 'the file is on disk');
}
$ok = $upload($dir, 'ok.txt', 'plain');
$id = (int) ($ok['files'][0]['id'] ?? 0);
$say('upload, ok.txt (legit)', 0, ! $mk('ok.txt') || $id < 1, 'a plain file was refused');

$upload("$dir/../..", 'probe_escape.txt', 'escaped');
$upload('../..', 'probe_escape.txt', 'escaped');
$say('upload, into a folder above the library', 0, is_file(dirname($libraryRoot) . '/probe_escape.txt') || is_file(dirname($libraryRoot, 2) . '/probe_escape.txt') || is_file("$libraryRoot/probe_escape.txt"), 'the file left the library');
@unlink(dirname($libraryRoot) . '/probe_escape.txt');
@unlink(dirname($libraryRoot, 2) . '/probe_escape.txt');
@unlink("$libraryRoot/probe_escape.txt");

$post('admin/media/rename', ['mode' => 'library', 'folder' => $dir, 'ids' => [$id], 'file_name' => '../escaped.txt']);
$say('rename, ok.txt to ../escaped.txt', 0, is_file("$libraryRoot/escaped.txt") || is_file(dirname($libraryRoot) . '/escaped.txt'), 'the file left its folder');
@unlink("$libraryRoot/escaped.txt");
$post('admin/media/rename', ['mode' => 'library', 'folder' => $dir, 'ids' => [$id], 'file_name' => 'moved.php']);
$say('rename, to moved.php', 0, $mk('moved.php'), 'the file is on disk');
$post('admin/media/move', ['mode' => 'library', 'folder' => $dir, 'ids' => [$id], 'to' => '../..']);
$say('move, to a folder above the library', 0, is_file(dirname($libraryRoot) . '/escaped.txt') || is_file("$libraryRoot/escaped.txt"), 'the file left the library');
@unlink(dirname($libraryRoot) . '/escaped.txt');
$post('admin/media/folders', ['mode' => 'library', 'folder' => $dir, 'new_folder' => '.hidden']);
$say('folder, named .hidden', 0, is_dir("$libraryRoot/$dir/.hidden"), 'the folder was made');
$post('admin/media/folders', ['mode' => 'library', 'folder' => $dir, 'new_folder' => 'a.php']);
$say('folder, named a.php', 0, is_dir("$libraryRoot/$dir/a.php"), 'the folder was made');

// A file is handed out by its id, never by a path, and what a browser could run is neither shown in the page nor allowed to act.
$svg  = $upload($dir, 'evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
$html = $upload($dir, 'evil.html', '<script>alert(1)</script>');
$txt  = $upload($dir, 'seen.txt', 'plain text');
$link = fn(array $answer): string => 'media/' . (int) ($answer['files'][0]['id'] ?? 0) . '/x';
$h    = $headersOf($link($txt));
$say('media link, a text file (legit)', (int) ($h['status'] ?? 0), ($h['status'] ?? '') !== '200' || ! str_starts_with($h['content-disposition'] ?? '', 'inline'), 'a plain file could not be read by its link');
$h = $headersOf($link($svg));
$say('media link, svg is sandboxed', (int) ($h['status'] ?? 0), ! str_contains($h['content-security-policy'] ?? '', 'sandbox') || ($h['x-content-type-options'] ?? '') !== 'nosniff', 'a script in the file could run in the site');
$h = $headersOf($link($html));
$say('media link, html is a download', (int) ($h['status'] ?? 0), ! str_starts_with($h['content-disposition'] ?? '', 'attachment'), 'a page of the user would be shown in the site');
// The site answers an unknown path with a page, so what counts is whether the file itself comes back.
[$status, $body] = web($base, $anon, 'GET', 'writable/uploads/' . $dir . '/seen.txt');
$say('media file, by its path on disk', $status, str_contains($body, 'plain text'), 'the folder is reachable from the web');
[$status, $body] = web($base, $anon, 'GET', 'uploads/' . $dir . '/seen.txt');
$say('media file, by its path in the public folder', $status, str_contains($body, 'plain text'), 'the file is in the public folder');
[$status] = web($base, $anon, 'GET', 'media/999999/none.txt');
$say('media link, a file that does not exist', $status, $status !== 404, 'it did not answer 404');

if ($stored !== []) {
    $post('admin/media/delete', ['mode' => 'library', 'folder' => $dir, 'ids' => $stored]);
}
if (is_dir("$libraryRoot/$dir")) {
    foreach (glob("$libraryRoot/$dir/{,.}*", GLOB_BRACE) ?: [] as $f) {
        is_file($f) && @unlink($f);
    }
    @rmdir("$libraryRoot/$dir");
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
    'admin/roles'                     => [true, false, false],
    'admin/abilities'                 => [true, false, false],
    'admin/tokens'                    => [true, true, false],
    'admin/profile'                   => [true, true, true],
    'admin/entries'                   => [true, true, false],
    'admin/media'                     => [true, true, false],
    'admin/media/picker'              => [true, true, false],
    'admin/media/frame'               => [true, true, false],
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
    'admin/media/upload'                             => [true, true, false],
    'admin/media/delete'                             => [true, true, false],
    'admin/media/move'                               => [true, true, false],
    'admin/media/rename'                             => [true, true, false],
    'admin/media/folders'                            => [true, true, false],
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
        $act($sa, $satoken, 'admin/users', ['username' => $user, 'email' => $user . '@example.test', 'password' => 'Walrus-Copper-Lantern-2026', 'roles' => [$role]]);
    }

    // The attacks below mean nothing if the accounts were not made, so make sure they exist and can sign in.
    foreach (['probe_manager', 'probe_editor'] as $user) {
        $made = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE username = ' . $pdo->quote($user))->fetchColumn();
        [$cj] = signIn($base, $user . '@example.test', 'Walrus-Copper-Lantern-2026');
        [$status] = web($base, $cj, 'GET', 'admin/profile');
        $say("probe account $user exists and signs in", $status, $made !== 1 || $status !== 200, 'the account was not made, so the attacks test nothing');
    }

    // A person who may manage users must not reach the superadmin role, or an account that has it.
    [$mj, $mtoken] = signIn($base, 'probe_manager@example.test', 'Walrus-Copper-Lantern-2026');
    // What the manager may do must work, or the refusals below say nothing.
    [$ost, , $oloc] = $act($mj, $mtoken, 'admin/users', ['username' => 'probe_plain', 'email' => 'probe_plain@example.test', 'password' => 'Walrus-Copper-Lantern-2026', 'roles' => []]);
    $say('users, a manager makes an ordinary account (control)', $ost, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'probe_plain'")->fetchColumn() !== 1, 'the manager could not even make an ordinary account, status ' . $ost . ' ' . $oloc);
    $act($mj, $mtoken, 'admin/users/' . $pdo->query("SELECT id FROM users WHERE username = 'probe_manager'")->fetchColumn(), ['username' => 'probe_manager', 'email' => 'probe_manager@example.test', 'roles' => ['probe_users', 'superadmin']]);
    $say('users, a manager makes themselves superadmin', 200, in_array('superadmin', $rolesOf('probe_manager'), true), 'the person reached the superadmin role');
    $act($mj, $mtoken, 'admin/users', ['username' => 'probe_friend', 'email' => 'probe_friend@example.test', 'password' => 'Walrus-Copper-Lantern-2026', 'roles' => ['superadmin']]);
    $say('users, a manager makes a superadmin account', 200, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'probe_friend'")->fetchColumn() > 0 && in_array('superadmin', $rolesOf('probe_friend'), true), 'a superadmin account was made');
    $act($mj, $mtoken, 'admin/users/' . $pdo->query("SELECT id FROM users WHERE username = 'capture_admin'")->fetchColumn(), ['username' => 'capture_admin', 'email' => 'capture_admin@example.test', 'password' => 'Hijacked-Passw0rd-2026!', 'roles' => ['superadmin']]);
    [$cj] = signIn($base, 'capture_admin@example.test', 'Hijacked-Passw0rd-2026!', false);
    [$status] = web($base, $cj, 'GET', 'admin/models');
    $say('users, a manager resets a superadmin password', $status, $status === 200, 'the password of a superadmin was changed');
    $act($mj, $mtoken, 'admin/users/' . $pdo->query("SELECT id FROM users WHERE username = 'capture_admin'")->fetchColumn() . '/delete', []);
    $say('users, a manager deletes a superadmin', 200, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'capture_admin'")->fetchColumn() === 0, 'a superadmin account was deleted');

    // A person who may manage roles can hand out only what they have.
    [$ej, $etoken] = signIn($base, 'probe_editor@example.test', 'Walrus-Copper-Lantern-2026');
    [$est, , $eloc] = $act($ej, $etoken, 'admin/roles', ['name' => 'probe_plainrole', 'label' => 'Plain role']);
    $say('roles, an editor makes an ordinary role (control)', $est, (int) $pdo->query("SELECT COUNT(*) FROM hyper_roles WHERE name = 'probe_plainrole'")->fetchColumn() !== 1, 'the editor could not make an ordinary role, status ' . $est . ' ' . $eloc);
    $act($ej, $etoken, 'admin/roles/probe_roles', ['label' => 'Probe roles', 'abilities' => ['roles.manage', 'users.manage', 'models.manage']]);
    $held = $pdo->query("SELECT ability FROM hyper_role_abilities WHERE role = 'probe_roles' ORDER BY ability")->fetchAll(PDO::FETCH_COLUMN);
    $say('roles, an editor gives their own role more power', 200, $held !== ['roles.manage'], 'the role now has ' . implode(', ', $held));
    $act($ej, $etoken, 'admin/roles/superadmin', ['label' => 'Everyone', 'abilities' => []]);
    $say('roles, an editor renames the superadmin role', 200, (string) $pdo->query("SELECT label FROM hyper_roles WHERE name = 'superadmin'")->fetchColumn() === 'Everyone', 'the superadmin role was changed');
    $act($ej, $etoken, 'admin/roles', ['name' => 'probe_copy', 'label' => 'Copy', 'copy' => 'developer']);
    $say('roles, an editor copies a role with more power', 200, (int) $pdo->query("SELECT COUNT(*) FROM hyper_roles WHERE name = 'probe_copy'")->fetchColumn() > 0 && (int) $pdo->query("SELECT COUNT(*) FROM hyper_role_abilities WHERE role = 'probe_copy'")->fetchColumn() > 0, 'a role with abilities the editor lacks was copied');

    // Saving a model must not hand out actions the person lacks. A person who may manage models, and holds nothing on a
    // model, tries to give their own role every action on it, and a higher role the person may not handle too.
    $act($sa, $satoken, 'admin/roles', ['name' => 'probe_models', 'label' => 'Probe models', 'abilities' => ['models.manage']]);
    $act($sa, $satoken, 'admin/users', ['username' => 'probe_modeler', 'email' => 'probe_modeler@example.test', 'password' => 'Walrus-Copper-Lantern-2026', 'roles' => ['probe_models']]);
    [$kj, $ktoken] = signIn($base, 'probe_modeler@example.test', 'Walrus-Copper-Lantern-2026');
    [$kst, , $kloc] = $act($kj, $ktoken, 'admin/models/3', ['name' => 'Linked', 'fields' => '[{"id":"title","label":"Title","type":"text"}]', 'access' => ['probe_models' => ['read', 'update', 'delete'], 'developer' => ['read']]]);
    $saved = (string) $pdo->query('SELECT `fields` FROM hyper_models WHERE id = 3')->fetchColumn();
    $say('models, the manager saved the model, so the grid was really tried', 200, ! str_contains($saved, '"title"') || str_contains($saved, 'related_model'), 'the save did not go through, status ' . $kst . ' ' . $kloc);
    $granted = $pdo->query("SELECT COUNT(*) FROM hyper_model_permissions WHERE model_id = 3 AND role = 'probe_models'")->fetchColumn();
    $say('models, a manager gives their own role actions they do not hold', 200, (int) $granted > 0, $granted . ' rows were written');
    $before = (int) $pdo->query("SELECT COUNT(*) FROM hyper_model_permissions WHERE model_id = 3 AND role = 'developer'")->fetchColumn();
    $say('models, a manager removes the actions of a higher role', 200, $before < 5, 'the developer role kept ' . $before . ' of 5 actions');

    // A new model has no rights yet, and what the person holds on other models must not stand in for them.
    $pdo->exec("INSERT INTO hyper_model_permissions (model_id, role, action) VALUES (1, 'probe_models', 'read')");
    $act($kj, $ktoken, 'admin/models', ['name' => 'Probe new model', 'fields' => '[{"id":"title","label":"Title","type":"text"}]', 'access' => ['probe_models' => ['read'], 'developer' => ['read']]]);
    $newId = (int) $pdo->query("SELECT id FROM hyper_models WHERE name = 'Probe new model'")->fetchColumn();
    $say('models, a manager makes a model and sets its access', 200, $newId === 0 || (int) $pdo->query("SELECT COUNT(*) FROM hyper_model_permissions WHERE model_id = $newId AND role IN ('probe_models', 'developer')")->fetchColumn() > 0, $newId === 0 ? 'the model was not made, so nothing was tried' : 'a new model got access from rights held on another model');

    // An ability that a higher role has cannot be deleted by someone who may not change that role.
    $pdo->exec("INSERT INTO hyper_abilities (name, label, created_at) VALUES ('probe.held', 'Held', NOW())");
    $pdo->exec("INSERT INTO hyper_role_abilities (role, ability) VALUES ('developer', 'probe.held')");
    $act($ej, $etoken, 'admin/abilities/probe.held/delete', []);
    $say('abilities, an editor deletes one that a higher role has', 200, (int) $pdo->query("SELECT COUNT(*) FROM hyper_abilities WHERE name = 'probe.held'")->fetchColumn() === 0, 'an ability held by a higher role was deleted');

    // Only a person who manages settings can turn on two-factor sign in for the site, whoever posts the form.
    [$dj, $dtoken] = signIn($base, 'capture_dev@example.test', 'Capture-Dev-2026');
    $act($dj, $dtoken, 'admin/settings/update', ['general_datatable_entries_per_page' => '25', 'two_factor' => '1']);
    $say('settings, a developer turns on two-factor for the site', 200, (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE `key` = 'Hyper.twoFactor' AND value IN ('1', 'true')")->fetchColumn() > 0, 'a site setting was changed without the ability');

    // The password is asked for before the email or the password of an account changes.
    $act($dj, $dtoken, 'admin/profile', ['username' => 'capture_dev', 'email' => 'taken_over@example.test', 'current_password' => 'not the password']);
    $say('profile, change the email without the current password', 200, (int) $pdo->query("SELECT COUNT(*) FROM auth_identities WHERE secret = 'taken_over@example.test'")->fetchColumn() > 0, 'the email changed without the password');

    // Clean up, in the order that the guards allow.
    $pdo->exec("DELETE FROM auth_groups_users WHERE user_id IN (SELECT id FROM users WHERE username IN ('probe_manager', 'probe_editor', 'probe_friend', 'probe_modeler', 'probe_plain'))");
    $pdo->exec("DELETE FROM auth_identities WHERE user_id IN (SELECT id FROM users WHERE username IN ('probe_manager', 'probe_editor', 'probe_friend', 'probe_modeler', 'probe_plain'))");
    $pdo->exec("DELETE FROM users WHERE username IN ('probe_manager', 'probe_editor', 'probe_friend', 'probe_modeler', 'probe_plain')");
    $pdo->exec("DELETE FROM hyper_model_permissions WHERE role LIKE 'probe\_%'");
    $pdo->exec("DELETE FROM hyper_role_abilities WHERE role LIKE 'probe\\_%'");
    $pdo->exec("DELETE FROM hyper_roles WHERE name LIKE 'probe\\_%'");
    $pdo->exec("DELETE FROM hyper_abilities WHERE name LIKE 'probe.%'");
    $pdo->exec("UPDATE hyper_roles SET label = 'Super Admin' WHERE name = 'superadmin'");
}

echo "$exposed probes were exposed in all\n";
exit($exposed === 0 && $blocked === 0 ? 0 : 1);
