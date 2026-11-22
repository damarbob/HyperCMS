<?php
declare(strict_types=1);

// Capture the admin pages and write flows. Run it on a freshly restored database.
// usage: php admin_harness.php capture <baseUrl> <outDir> <envFile> [watchedFile]
require __DIR__ . '/lib.php';

if (($argv[1] ?? '') !== 'capture' || count($argv) < 5) {
    fwrite(STDERR, "usage: admin_harness.php capture <baseUrl> <outDir> <envFile> [watchedFile]\n");
    exit(2);
}
[, , $base, $out, $envFile] = $argv;
$watched = $argv[5] ?? __DIR__ . '/watched.php';
if (! is_file($watched)) {
    fwrite(STDERR, "Watched-tables file not found: $watched\n");
    exit(2);
}
$base = rtrim($base, '/') . '/';
@mkdir($out, 0777, true);

function j(mixed $v): string
{
    return json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '?';
}

final class Client
{
    public array $cookies = [];

    public function __construct(private string $base)
    {
    }

    public ?string $token = null;

    public function csrf(): ?string
    {
        return $this->token;
    }

    public function request(string $method, string $path, ?array $form = null, bool $json = false): array
    {
        $h = [];
        if ($this->cookies) {
            $h[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        }
        $http = ['method' => $method, 'ignore_errors' => true, 'timeout' => 90, 'follow_location' => 0, 'max_redirects' => 0];
        if ($json) {
            $h[] = 'Accept: application/json';
            $h[] = 'X-Requested-With: XMLHttpRequest';
        }
        if ($form !== null) {
            $h[] = 'Content-Type: application/x-www-form-urlencoded';
            if (($t = $this->csrf()) !== null) {
                $h[] = 'X-HYPER-CSRF-TOKEN: ' . $t;
            }
            $http['content'] = http_build_query($form + ['hyper_csrf_token' => (string) $this->token]);
        }
        $http['header'] = implode("\r\n", $h);
        $body = @file_get_contents($this->base . $path, false, stream_context_create(['http' => $http]));
        $status = 0;
        $loc = null;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int) $m[1];
            } elseif (stripos($line, 'Location:') === 0) {
                $loc = trim(substr($line, 9));
            } elseif (stripos($line, 'Set-Cookie:') === 0) {
                [$kv] = explode(';', trim(substr($line, 11)), 2);
                [$k, $v] = array_pad(explode('=', $kv, 2), 2, '');
                if ($v === '' || $v === 'deleted') {
                    unset($this->cookies[$k]);
                } else {
                    $this->cookies[$k] = $v;
                }
            }
        }
        if (preg_match('/name="hyper_csrf_token"\s+value="([a-f0-9]+)"/', (string) $body, $tm)) {
            $this->token = $tm[1];
        }
        return [$status, (string) $body, $loc];
    }
}

// database access for the state snapshots
$pdo = connect(dbSettings($envFile), true);
$queries = require $watched;

function snapshot(PDO $pdo, array $queries): array
{
    $out = [];
    foreach ($queries as $table => $sql) {
        $out[$table] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    return $out;
}

function changes(array $before, array $after): array
{
    $out = [];
    foreach ($after as $table => $rows) {
        $key = fn($r) => isset($r['id']) ? (string) $r['id'] : implode('/', array_slice($r, 0, 2));
        $old = [];
        foreach ($before[$table] ?? [] as $r) {
            $old[$key($r)] = $r;
        }
        $new = [];
        foreach ($rows as $r) {
            $new[$key($r)] = $r;
        }
        foreach ($new as $k => $r) {
            if (!isset($old[$k])) {
                $out[$table]['added'][] = $r;
            } elseif ($old[$k] != $r) {
                $out[$table]['changed'][] = ['from' => $old[$k], 'to' => $r];
            }
        }
        foreach ($old as $k => $r) {
            if (!isset($new[$k])) {
                $out[$table]['removed'][] = $r;
            }
        }
    }
    return $out;
}

$summary = [];
$save = function (string $name, array $record) use ($out, &$summary): void {
    file_put_contents("$out/$name.json", j($record) . "\n");
    $summary[] = [$name, $record['status'] ?? '', $record['location'] ?? ''];
};

$page = function (array $r, string $name = '') use ($out): array {
    [$status, $body, $loc] = $r;
    // mask the CSRF tokens and the Debug Toolbar's request timestamp, which vary on every call
    $n = (string) preg_replace('/data-time="[0-9.]+"/', 'data-time="<t>"', $body);
    $n = (string) preg_replace('/[a-f0-9]{32}/', '<hex32>', $n);
    if ($status >= 500) {
        // A development error page prints source line numbers, so only the status is stable.
        return ['status' => $status, 'location' => null, 'title' => null, 'bytes' => null, 'sha1' => null];
    }
    if ($name !== '') {
        @mkdir("$out/bodies", 0777, true);
        file_put_contents("$out/bodies/$name.txt", $n);
    }
    $title = preg_match('~<title>(.*?)</title>~is', $n, $m) ? trim((string) preg_replace('/\s+/', ' ', $m[1])) : null;
    return [
        'status'   => $status,
        'location' => $loc ? (string) preg_replace('~^https?://[^/]+~', '', $loc) : null,
        'title'    => $title,
        'bytes'    => strlen($n),
        'sha1'     => sha1($n),
    ];
};

$c = new Client($base);

// signed out
$save('p00_anon_admin_entries', ['request' => 'GET admin/entries'] + $page($c->request('GET', 'admin/entries')));

// sign in through the real form, with its CSRF token
$c->request('GET', 'auth/login'); // sets the session cookie and reads the CSRF token from the form
$login = $c->request('POST', 'auth/login', ['email' => 'capture_admin@example.test', 'password' => 'Capture-Admin-2026']);
$c->request('GET', 'admin/model/1/new'); // a page with a form, to pick up a token for the signed-in session
$save('p01_login', ['request' => 'POST auth/login'] + $page($login));

$pages = [
    'admin/dashboard', 'admin/entries', 'admin/model/2/new', 'admin/model/1/24/edit', 'admin/model/1/999/edit',
    'admin/model/1/1/history', 'admin/model/1/999/history', 'admin/model/1', 'admin/model/1/new', 'admin/model/1/1/edit',
    'admin/model/2', 'admin/model/3', 'admin/model/3/new', 'admin/model/4', 'admin/model/5', 'admin/model/999',
    'admin/models', 'admin/models/new', 'admin/models/1/edit', 'admin/models/3/edit', 'admin/models/999/edit',
    'admin/model-data/1', 'admin/model-data/999',
    'admin/settings', 'admin/settings/models',
    'admin/models/2/edit', // the public model, with its box ticked
];
foreach ($pages as $i => $p) {
    $name = 'p' . str_pad((string) ($i + 2), 2, '0', STR_PAD_LEFT) . '_' . preg_replace('/[^a-z0-9]+/i', '_', $p);
    $r = $c->request('GET', $p);
    if ($p === 'admin/dashboard') {
        // the dashboard shows a random quote fetched from an external site
        $r[1] = (string) preg_replace('~(<p class="subtitle">).*?(</p>)~s', '$1<quote>$2', $r[1]);
        $r[1] = (string) preg_replace('~"quote":"[^"]*"~', '"quote":"<quote>"', $r[1]);
    }
    $save($name, ['request' => "GET $p"] + $page($r, $name));
}

// The list endpoints as the admin screens call them, with the session cookie and no token.
$apiCalls = [
    'a01_api_models'           => ['POST', 'api/v1/models', ['draw' => 1, 'start' => 0, 'length' => 2]],
    'a02_api_model_data'       => ['POST', 'api/v1/model-data', ['draw' => 1, 'id' => 1, 'order' => [['column' => 0, 'dir' => 'asc']], 'columns' => [['data' => 'name']]]],
    'a06_api_entries_resource' => ['GET', 'api/v1/entries-resource?limit=2', null],
];
foreach ($apiCalls as $name => [$method, $path, $form]) {
    [$status, $body] = $c->request($method, $path, $form, true);
    $save($name, ['request' => ['method' => $method, 'path' => $path, 'form' => $form], 'status' => $status, 'body' => json_decode($body, true)]);
}

// The same endpoints for a signed-in account outside the staff groups. Model 5 is restricted to developers.
$u = new Client($base);
$u->request('GET', 'auth/login');
$u->request('POST', 'auth/login', ['email' => 'capture_user@example.test', 'password' => 'Capture-User-2026']);
$userCalls = [
    'u01_api_models'           => ['POST', 'api/v1/models', ['draw' => 1, 'start' => 0, 'length' => 2]],
    'u05_api_entries_resource' => ['GET', 'api/v1/entries-resource?limit=2', null],
];
foreach ($userCalls as $name => [$method, $path, $form]) {
    [$status, $body] = $u->request($method, $path, $form, true);
    $save($name, ['request' => ['method' => $method, 'path' => $path, 'form' => $form, 'account' => 'capture_user'], 'status' => $status, 'body' => json_decode($body, true)]);
}

// write flows, each followed by the rows it changed
$j = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$productFields = fn(string $sku) => ['sku' => $sku, 'price' => '5.00', 'category' => 'a', 'added' => '2026-04-01T08:00'];
$articleModelFields = $j([
    ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
    ['id' => 'body', 'label' => 'Body', 'type' => 'textarea'],
    ['id' => 'views', 'label' => 'Views', 'type' => 'number'],
    ['id' => 'summary', 'label' => 'Summary', 'type' => 'text'],
]);
$flows = [
    'w01_entry_create'        => ['admin/model/2', ['fields' => $productFields('SKU-NEW')]],
    'w02_entry_create_bad'    => ['admin/model/2', ['fields' => ['sku' => 'SKU-BAD', 'price' => 'not a number']]],
    'w03_entry_update'        => ['admin/model/1/1', ['fields' => ['title' => 'Edited by flow', 'body' => '<p>x</p>', 'views' => '99', 'published_at' => '', 'tags' => ['tech']]]],
    'w04_entry_update_bad'    => ['admin/model/1/1', ['fields' => ['title' => '', 'views' => 'many']]],
    'w05_entry_delete_single' => ['admin/model/1/3/trash', []],
    'w06_entry_delete_bulk'   => ['admin/model/1/trash', ['ids' => [4, 5]]],
    'w07_entry_delete_none'   => ['admin/model/1/trash', []],
    'w08_entry_restore'       => ['admin/model/1/restore', ['ids' => [24]]],
    'w09_entry_restore_none'  => ['admin/model/1/restore', []],
    'w10_entry_purge'         => ['admin/model/1/purge', []],
    'w11_entry_purge_empty'   => ['admin/model/1/purge', []],
    'w22_entry_trash_other_model' => ['admin/model/1/trash', ['ids' => [43]]],
    'w23_entry_history_clear' => ['admin/model/1/1/history/clear', []],
    'w12_model_create'        => ['admin/models', ['name' => 'Gallery', 'fields' => $j([['id' => 'caption', 'label' => 'Caption', 'type' => 'text']]), 'group' => 'Media', 'user_groups' => ['superadmin'], 'icon' => 'image']],
    'w13_model_create_bad'    => ['admin/models', ['name' => 'ab']],
    'w14_model_update'        => ['admin/models/1', ['name' => 'Article', 'fields' => $articleModelFields, 'group' => 'Content', 'user_groups' => ['superadmin', 'admin'], 'icon' => 'file-text']],
    'w15_model_delete_single' => ['admin/models/3/delete', []],
    'w16_model_delete_bulk'   => ['admin/models/delete', ['ids' => [2]]],
    'w17_model_restore'       => ['admin/models/restore', ['ids' => [4]]],
    'w18_model_purge'         => ['admin/models/purge-deleted', []],
    'w19_model_data_clear'    => ['admin/model-data/clear-history/1', []],
    'w20_model_public_on'     => ['admin/models/1', ['name' => 'Article', 'fields' => $articleModelFields, 'group' => 'Content', 'user_groups' => ['superadmin', 'admin'], 'icon' => 'file-text', 'public' => '1']],
    'w21_model_public_off'    => ['admin/models/1', ['name' => 'Article', 'fields' => $articleModelFields, 'group' => 'Content', 'user_groups' => ['superadmin', 'admin'], 'icon' => 'file-text']],
];
$prev = snapshot($pdo, $queries);
foreach ($flows as $name => [$path, $form]) {
    [$status, $body, $loc] = $c->request('POST', $path, $form, true);
    $decoded = json_decode($body, true);
    $cur = snapshot($pdo, $queries);
    $save($name, [
        'request'  => ['method' => 'POST', 'path' => $path, 'form' => $form],
        'status'   => $status,
        'location' => $loc ? (string) preg_replace('~^https?://[^/]+~', '', $loc) : null,
        'body'     => json_last_error() === JSON_ERROR_NONE ? $decoded : substr(trim((string) preg_replace('/\s+/', ' ', strip_tags($body))), 0, 200),
        'changes'  => changes($prev, $cur),
    ]);
    $prev = $cur;
}

foreach ($summary as [$name, $status, $loc]) {
    echo str_pad($name, 34) . str_pad((string) $status, 5) . ($loc ?: '') . PHP_EOL;
}
echo count($summary) . " captures saved to $out\n";
