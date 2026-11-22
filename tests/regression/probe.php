<?php
declare(strict_types=1);

// Hostile sort and find values against the DataTables endpoints, on a throwaway server.
// A request is EXPOSED when its text reaches SQL and makes the database complain about it.
// The probes sign in first, so they test the queries and are not stopped at the login check.
// usage: php probe.php <baseUrl>
$base = rtrim($argv[1] ?? 'http://localhost:8099', '/') . '/';

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

// History pages and their clear actions, as an account in the `user` group. Model 5 is restricted to developers and entry 43 belongs to it.
[$ujar] = signIn($base, 'capture_user@example.test', 'Capture-User-2026');
foreach (['admin/model-data/5' => 'model history page', 'admin/entry-data/43' => 'entry history page'] as $path => $label) {
    [$status] = web($base, $ujar, 'GET', $path);
    $say("history, $label, user account", $status, $status === 200, 'a restricted model was opened');
}
[$status] = web($base, $ujar, 'GET', 'admin/model-data/1');
$say('history, model 1 page (legit)', $status, $status !== 200, 'an open model was refused');
[$ujar, $utoken] = signIn($base, 'capture_user@example.test', 'Capture-User-2026');
foreach (['admin/model-data/clear-history/5' => 'clear model history', 'admin/entry-data/clear-history/43' => 'clear entry history'] as $path => $label) {
    [$status, , $loc] = web($base, $ujar, 'POST', $path, http_build_query(['hyper_csrf_token' => $utoken]), ['Content-Type: application/x-www-form-urlencoded', 'X-HYPER-CSRF-TOKEN: ' . $utoken]);
    // Allowed means the action ran, which answers 200, or redirects to the entries list with a success flash.
    $say("history, $label, user account", $status, $status === 200 || ($status >= 300 && $status < 400 && str_contains((string) $loc, 'admin/entries')), 'a restricted model was changed');
}

echo "$exposed probes were exposed in all\n";
exit($exposed === 0 && $blocked === 0 ? 0 : 1);
