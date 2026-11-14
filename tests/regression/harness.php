<?php
declare(strict_types=1);

// Capture what the API endpoints answer, or compare two folders of captures.
//   php harness.php capture <baseUrl> <outDir>
//   php harness.php compare <dirA> <dirB>
$mode = $argv[1] ?? '';

function j(mixed $v): string
{
    return json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '?';
}

function call(string $method, string $url, ?array $form = null, ?array $json = null, array $headers = []): array
{
    $http = ['method' => $method, 'ignore_errors' => true, 'timeout' => 60];
    $h = $headers;
    if ($form !== null) {
        $h[] = 'Content-Type: application/x-www-form-urlencoded';
        $http['content'] = http_build_query($form);
    }
    if ($json !== null) {
        $h[] = 'Content-Type: application/json';
        $http['content'] = json_encode($json);
    }
    $h[] = 'Accept: application/json';
    $http['header'] = implode("\r\n", $h);
    $body = @file_get_contents($url, false, stream_context_create(['http' => $http]));
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $status = (int) $m[1];
        }
    }
    return [$status, (string) $body];
}

if ($mode === 'compare') {
    [, , $a, $b] = $argv;
    $names = array_unique(array_merge(array_map('basename', glob("$a/*.json") ?: []), array_map('basename', glob("$b/*.json") ?: [])));
    sort($names);
    $diff = 0;
    // Git may check the baseline out with Windows line endings, so those are not a difference.
    $read = fn(string $file) => is_file($file) ? str_replace("\r\n", "\n", (string) file_get_contents($file)) : null;
    foreach ($names as $n) {
        $x = $read("$a/$n");
        $y = $read("$b/$n");
        if ($x !== $y) {
            $diff++;
            echo 'DIFF ' . $n . ($x === null ? ' (missing in A)' : ($y === null ? ' (missing in B)' : '')) . PHP_EOL;
        }
    }
    echo count($names) . " files compared, $diff differ\n";
    exit($diff === 0 ? 0 : 1);
}

if ($mode !== 'capture') {
    fwrite(STDERR, "usage: harness.php capture <baseUrl> <outDir> | compare <dirA> <dirB>\n");
    exit(2);
}

[, , $base, $out] = $argv;
$base = rtrim($base, '/') . '/';
@mkdir($out, 0777, true);

$dt = fn(array $x = []) => array_merge(['draw' => 1], $x);
$m1 = fn(array $x = []) => $dt(array_merge(['id' => 1, 'start' => 0, 'length' => 10], $x));
$ord = fn(string $col, string $dir) => ['order' => [['column' => 0, 'dir' => $dir]], 'columns' => [['data' => $col]]];

$post = [
    // models
    'models_all'            => ['api/v1/models', $dt()],
    'models_page1'          => ['api/v1/models', $dt(['start' => 0, 'length' => 2])],
    'models_page2'          => ['api/v1/models', $dt(['start' => 2, 'length' => 2])],
    'models_search_art'     => ['api/v1/models', $dt(['search' => ['value' => 'Art'], 'start' => 0, 'length' => 10])],
    'models_trash'          => ['api/v1/models', $dt(['trash' => 'true'])],
    'models_order_name_asc' => ['api/v1/models', $dt($ord('name', 'asc'))],
    // entries across models
    'entries_page1'         => ['api/v1/entries', $dt(['start' => 0, 'length' => 10])],
    'entries_page3'         => ['api/v1/entries', $dt(['start' => 20, 'length' => 10])],
    'entries_model1'        => ['api/v1/entries', $dt(['model_id' => 1])],
    'entries_search_apple'  => ['api/v1/entries', $dt(['search' => ['value' => 'apple'], 'start' => 0, 'length' => 10])],
    'entries_search_markup' => ['api/v1/entries', $dt(['search' => ['value' => 'markup'], 'start' => 0, 'length' => 10])],
    'entries_trash'         => ['api/v1/entries', $dt(['trash' => 'true'])],
    'entries_order_model'   => ['api/v1/entries', $dt(array_merge($ord('model_name', 'asc'), ['start' => 0, 'length' => 10]))],
    'entries_ids'           => ['api/v1/entries', $dt(['ids' => [1, 2, 3]])],
    // entries of one model
    'model1_page1'          => ['api/v1/model', $m1()],
    'model1_page2'          => ['api/v1/model', $m1(['start' => 10])],
    'model1_page3'          => ['api/v1/model', $m1(['start' => 20])],
    'model1_all'            => ['api/v1/model', $m1(['length' => -1])],
    'model1_search_apple'   => ['api/v1/model', $m1(['search' => ['value' => 'apple']])],
    'model1_search_markup'  => ['api/v1/model', $m1(['search' => ['value' => 'markup']])],
    'model1_search_none'    => ['api/v1/model', $m1(['search' => ['value' => 'zzznomatch']])],
    'model1_order_title_asc'  => ['api/v1/model', $m1($ord('title', 'asc'))],
    'model1_order_title_desc' => ['api/v1/model', $m1($ord('title', 'desc'))],
    'model1_order_views_asc'  => ['api/v1/model', $m1($ord('views', 'asc'))],
    'model1_order_pub_desc'   => ['api/v1/model', $m1($ord('published_at', 'desc'))],
    'model1_order_id_desc'    => ['api/v1/model', $m1($ord('id', 'desc'))],
    'model1_trash'          => ['api/v1/model', $m1(['trash' => 'true'])],
    'model1_find_title'     => ['api/v1/model', $m1(['find' => ['field' => 'title', 'value' => 'apple']])],
    'model2_price_desc'     => ['api/v1/model', $m1(array_merge(['id' => 2, 'length' => -1], $ord('price', 'desc')))],
    'model2_trash'          => ['api/v1/model', $m1(['id' => 2, 'trash' => 'true'])],
    'model3_all'            => ['api/v1/model', $m1(['id' => 3, 'length' => -1])],
    'model_missing'         => ['api/v1/model', $m1(['id' => 999])],
    'model_noid'            => ['api/v1/model', $dt()],
    // model versions
    'modeldata_id1'         => ['api/v1/model-data', $dt(['id' => 1])],
    'modeldata_all'         => ['api/v1/model-data', $dt()],
    'modeldata_search'      => ['api/v1/model-data', $dt(['search' => ['value' => 'Art']])],
    'modeldata_id1_order_created_desc' => ['api/v1/model-data', $dt(array_merge(['id' => 1], $ord('date_created', 'desc')))],
    'modeldata_all_order_name_asc'     => ['api/v1/model-data', $dt($ord('name', 'asc'))],
    'modeldata_search_order_name'      => ['api/v1/model-data', $dt(array_merge(['search' => ['value' => 'Art']], $ord('name', 'asc')))],
    // entry versions
    'entrydata_id1'         => ['api/v1/entry-data', $dt(['id' => 1])],
    'entrydata_id1_page2'   => ['api/v1/entry-data', $dt(['id' => 1, 'start' => 1, 'length' => 1])],
    'entrydata_id2'         => ['api/v1/entry-data', $dt(['id' => 2])],
    'entrydata_noid'        => ['api/v1/entry-data', $dt()],
    'entrydata_missing'     => ['api/v1/entry-data', $dt(['id' => 9999])],
];

$summary = [];
$save = function (string $name, array $request, int $status, string $body) use ($out, &$summary): void {
    $decoded = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (is_array($decoded) && isset($decoded['access_token'])) {
            $decoded['access_token'] = '<masked>';
        }
        $payload = $decoded;
        if ($status >= 500 && is_array($decoded) && isset($decoded['message'])) {
            // keep the error type and message, drop the machine specific trace
            $payload = ['type' => $decoded['type'] ?? null, 'message' => $decoded['message']];
        }
    } else {
        $payload = ['nonJson' => substr(trim((string) preg_replace('/\s+/', ' ', strip_tags($body))), 0, 160)];
    }
    file_put_contents("$out/$name.json", j(['request' => $request, 'status' => $status, 'body' => $payload]) . "\n");
    $rows = is_array($payload) && isset($payload['data']) && is_array($payload['data']) ? count($payload['data']) : null;
    $summary[] = [$name, $status, strlen($body), $rows, is_array($payload) ? ($payload['recordsTotal'] ?? '') : '', is_array($payload) ? ($payload['recordsFiltered'] ?? '') : ''];
};

// Sign in first. Every list endpoint needs a login or a token.
[$s, $body] = call('POST', $base . 'api/v1/auth/login', null, ['email' => 'capture_admin@example.test', 'password' => 'Capture-Admin-2026']);
$save('auth_login', ['method' => 'POST', 'path' => 'api/v1/auth/login'], $s, $body);
$token = json_decode($body, true)['access_token'] ?? '';
[$s, $body] = call('POST', $base . 'api/v1/auth/login', null, ['email' => 'capture_admin@example.test', 'password' => 'wrong']);
$save('auth_login_bad', ['method' => 'POST', 'path' => 'api/v1/auth/login'], $s, $body);

foreach ($post as $name => [$path, $form]) {
    [$status, $body] = call('POST', $base . $path, $form, null, ['Authorization: Bearer ' . $token]);
    $save($name, ['method' => 'POST', 'path' => $path, 'form' => $form, 'auth' => 'bearer'], $status, $body);
}

// What a caller without a login gets. Model 2 is the public one.
$anon = [
    'anon_models'              => ['api/v1/models', $dt()],
    'anon_model_data'          => ['api/v1/model-data', $dt(['id' => 1])],
    'anon_entries'             => ['api/v1/entries', $dt()],
    'anon_entry_data'          => ['api/v1/entry-data', $dt(['id' => 1])],
    'anon_model1'              => ['api/v1/model', $m1()],
    'anon_model_noid'          => ['api/v1/model', $dt()],
    'anon_model2_public'       => ['api/v1/model', $m1(['id' => 2, 'length' => -1])],
    'anon_model2_public_trash' => ['api/v1/model', $m1(['id' => 2, 'length' => -1, 'trash' => 'true'])],
];
foreach ($anon as $name => [$path, $form]) {
    [$status, $body] = call('POST', $base . $path, $form);
    $save($name, ['method' => 'POST', 'path' => $path, 'form' => $form, 'auth' => 'none'], $status, $body);
}
[$s, $body] = call('POST', $base . 'api/v1/models', $dt(), null, ['Authorization: Bearer not-a-real-token']);
$save('badtoken_models', ['method' => 'POST', 'path' => 'api/v1/models', 'form' => $dt(), 'auth' => 'bad token'], $s, $body);

// A token works only for the staff groups. capture_user can log in, and its token is refused.
[$s, $body] = call('POST', $base . 'api/v1/auth/login', null, ['email' => 'capture_user@example.test', 'password' => 'Capture-User-2026']);
$save('user_login', ['method' => 'POST', 'path' => 'api/v1/auth/login', 'account' => 'capture_user'], $s, $body);
$userToken = json_decode($body, true)['access_token'] ?? '';
[$s, $body] = call('POST', $base . 'api/v1/models', $dt(), null, ['Authorization: Bearer ' . $userToken]);
$save('user_models', ['method' => 'POST', 'path' => 'api/v1/models', 'form' => $dt(), 'auth' => 'bearer of capture_user'], $s, $body);
[$s, $body] = call('GET', $base . 'api/v1/entries-resource', null, null, ['Authorization: Bearer ' . $userToken]);
$save('user_res_list', ['method' => 'GET', 'path' => 'api/v1/entries-resource', 'auth' => 'bearer of capture_user'], $s, $body);

$get = [
    'res_list'          => 'api/v1/entries-resource',
    'res_limit5_page2'  => 'api/v1/entries-resource?limit=5&page=2',
    'res_sort_id_asc'   => 'api/v1/entries-resource?sort=id&order=asc&limit=8',
    'res_sort_model'    => 'api/v1/entries-resource?sort=model_name&order=asc&limit=8',
    'res_q_article'     => 'api/v1/entries-resource?q=Article',
    'res_q_user'        => 'api/v1/entries-resource?q=capture_admin&limit=3',
    'res_model2'        => 'api/v1/entries-resource?model_id=2&limit=20',
    'res_limit_too_big' => 'api/v1/entries-resource?limit=500',
    'res_bad_sort'      => 'api/v1/entries-resource?sort=nope',
    'res_show_1'        => 'api/v1/entries-resource/1',
    'res_show_missing'  => 'api/v1/entries-resource/9999',
];
foreach ($get as $name => $path) {
    [$s, $body] = call('GET', $base . $path, null, null, ['Authorization: Bearer ' . $token]);
    $save($name, ['method' => 'GET', 'path' => $path, 'auth' => 'bearer'], $s, $body);
}
[$s, $body] = call('GET', $base . 'api/v1/entries-resource');
$save('res_no_token', ['method' => 'GET', 'path' => 'api/v1/entries-resource', 'auth' => 'none'], $s, $body);

foreach ($summary as [$name, $status, $bytes, $rows, $total, $filtered]) {
    echo str_pad($name, 26) . str_pad((string) $status, 5) . str_pad((string) $bytes, 8) . 'rows=' . str_pad((string) ($rows ?? '-'), 4) . "total=$total filtered=$filtered" . PHP_EOL;
}
echo count($summary) . " captures saved to $out\n";
