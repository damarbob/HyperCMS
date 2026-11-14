<?php
declare(strict_types=1);

// Hostile sort and find values against the open DataTables endpoints, on a throwaway server.
// A request is EXPOSED when its text reaches SQL and makes the database complain about it.
// usage: php probe.php <baseUrl>
$base = rtrim($argv[1] ?? 'http://localhost:8099', '/') . '/';

function post(string $url, array $form): array
{
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true, 'timeout' => 60,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json",
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

$exposed = 0;
foreach ($probes as $label => [$path, $form]) {
    [$status, $msg] = post($base . $path, $form);
    $bad = $status === 500 && preg_match('/SQL syntax|zz_no_such_table/i', $msg) === 1;
    $exposed += $bad ? 1 : 0;
    echo str_pad($label, 36) . str_pad((string) $status, 5) . ($bad ? 'EXPOSED  ' . substr($msg, 0, 60) : 'safe') . PHP_EOL;
}
echo "$exposed of " . count($probes) . " probes reach SQL\n";
exit($exposed === 0 ? 0 : 1);
