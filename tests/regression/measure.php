<?php
declare(strict_types=1);

// Measure what a saved list costs: the time and the PHP peak memory of a search and of a sort on a model field,
// on the first page and on a later page, over models of generated entries.
//
//   php measure.php build   <repoDir> <database> --entries=N [--large=PERCENT]
//   php measure.php run     <repoDir> <database> [--labels=a,b] [--memory=128M] [--out=file.json]
//   php measure.php drop    <repoDir> <database> --label=name     (remove one generated model and its entries)
//
// <database> must be a throwaway one that holds the seed and has been upgraded, for example the database a
// `check.ps1 -Database name` run leaves behind, because the generated models are added to it. The connection
// settings come from the repo's .env, and only the database name is replaced.
//
// `build` makes one model named `measure n<entries> large<percent>` and skips the work when it already exists, so a
// dataset is built once and reused by every run. The build keeps the server's binary log off for its own sessions. PERCENT of the entries (default 5) get a body of 1 to 3 MB, the size
// the stress test uses, and the rest get a body of about 1 KB. Every large body is written once, so a model of 50,000
// entries with 5 percent large bodies holds about 5 GB, and a model of large bodies only is 2 MB per entry.
//
// `run` sends each request in a PHP process of its own, which is what a web request is, and prints one line per
// request. A cold request has an empty cache, so it builds the saved list. A warm request finds the list cached by
// the one before it. The table of requests per model:
//   fixed          the list sorted by date modified in SQL, which needs no scan and shows the base cost of the page
//   search cold    page 1 of a search for `kiwi`, which 8 percent of the titles contain
//   search warm    page 1 again, then a page from the middle of the matches
//   search cold    the middle page with an empty cache, to show that it costs what page 1 costs
//   sort cold      page 1 of a sort on the title, ascending, then the same three requests as for search
require __DIR__ . '/lib.php';

const WORDS = ['alpha', 'harbor', 'quarterly', 'report', 'garden', 'orbit', 'lantern', 'maple', 'river', 'signal', 'bridge', 'copper', 'meadow', 'thread', 'window', 'cinder', 'harvest', 'island', 'journal', 'ledger', 'Éclair', 'Ångström', 'apple', 'Sample'];
const TERM  = 'kiwi';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
[, $mode, $repo, $database] = $argv + [null, null, null, null];
$repo = rtrim(str_replace('\\', '/', (string) (realpath((string) $repo) ?: $repo)), '/');
if (! in_array($mode, ['build', 'run', 'drop', 'one'], true) || ! is_dir($repo . '/app') || ! is_string($database) || $database === '') {
    fwrite(STDERR, "usage: measure.php build|run|drop <repoDir> <database> [options]\n");
    exit(2);
}
$options = [];
foreach (array_slice($argv, 4) as $arg) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    }
}
if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "The database name may hold letters, digits and underscores only\n");
    exit(2);
}

/** The label of a dataset, which is also its model name. */
function label(int $entries, int $large): string
{
    return "measure n$entries large$large";
}

if ($mode === 'run') {
    run($repo, $database, $options);
    exit(0);
}

// The database name from the command line wins over the one in .env, because a key with dots is read first.
$_ENV['database.default.database'] = $database;
$_SERVER['database.default.database'] = $database;

error_reporting(E_ALL);
ini_set('display_errors', '1');
define('FCPATH', $repo . '/public/');
chdir(FCPATH);
require $repo . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';

/** Boots the app the way `spark` does, and stops before the console runs. */
final class MeasureBoot extends CodeIgniter\Boot
{
    public static function bootMeasure(Config\Paths $paths): void
    {
        static::definePathConstants($paths);
        if (! defined('APP_NAMESPACE')) {
            static::loadConstants();
        }
        static::checkMissingExtensions();
        static::loadDotEnv($paths);
        static::defineEnvironment();
        static::loadEnvironmentBootstrap($paths);
        static::loadCommonFunctions();
        static::loadAutoloader();
        static::setExceptionHandler();
        static::initializeKint();
        static::autoloadHelpers();
        static::initializeCodeIgniter();
    }
}
MeasureBoot::bootMeasure($paths);
$bootPeak = memory_get_peak_usage();

if (db_connect()->database !== $database) {
    fwrite(STDERR, 'The app opened ' . db_connect()->database . ' and not ' . $database . ". Nothing was done.\n");
    exit(1);
}

switch ($mode) {
    case 'build':
        ini_set('memory_limit', '1G');
        build((int) ($options['entries'] ?? 0), (int) ($options['large'] ?? 5));
        break;
    case 'drop':
        dropDataset((string) ($options['label'] ?? ''));
        break;
    case 'one':
        one($options, $bootPeak);
        break;
}

/** The id of a generated model, or null. */
function datasetModel(string $label): ?array
{
    $row = db_connect()->query('SELECT id, sd_model_id FROM hyper_models WHERE name = ? AND deleted_at IS NULL', [$label])->getRowArray();

    return $row === null ? null : ['id' => (int) $row['id'], 'sd' => $row['sd_model_id'] === null ? null : (int) $row['sd_model_id']];
}

function build(int $entries, int $large): void
{
    if ($entries < 1 || $large < 0 || $large > 100) {
        fwrite(STDERR, "build needs --entries=N and an optional --large=0 to 100\n");
        exit(2);
    }
    $label = label($entries, $large);
    $existing = datasetModel($label);
    if ($existing !== null && (int) db_connect()->query('SELECT COUNT(*) AS n FROM hyper_entries WHERE model_id = ?', [$existing['id']])->getRow()->n > 0) {
        echo "$label exists, nothing to build\n";

        return;
    }
    if ($existing !== null) {
        // A build that stopped early left a model with no entries.
        dropDataset($label);
    }

    $definition = json_encode([
        ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
        ['id' => 'body', 'label' => 'Body', 'type' => 'textarea'],
        ['id' => 'views', 'label' => 'Views', 'type' => 'number'],
    ]);
    $modelId = service('modelStore')->create(['name' => $label, 'fields' => $definition], 1);
    $sdModel = datasetModel($label)['sd'];
    $db      = db_connect();
    $engine  = writeEngine($db);
    $tenant  = (int) config('HyperEngine')->tenantId;

    mt_srand(2026);
    $sentence = static function (int $words): string {
        $out = [];
        for ($i = 0; $i < $words; $i++) {
            $out[] = WORDS[mt_rand(0, count(WORDS) - 1)];
        }

        return implode(' ', $out);
    };
    // One block of text that large bodies repeat, so building a body costs little and the text still reads as words.
    $block = '';
    while (strlen($block) < 65536) {
        $block .= '<p>' . $sentence(mt_rand(30, 120)) . ' "quoted" back\\slash</p>';
    }

    $from  = strtotime('2025-01-01');
    $start = microtime(true);
    $batch = [];
    $bytes = 0;
    $done  = 0;

    $flush = static function () use (&$batch, &$bytes, &$done, $db, $engine, $tenant, $sdModel, $modelId, $entries, $start): void {
        if ($batch === []) {
            return;
        }
        $payloads = array_map(static fn (array $row): StarDust\Write\EntryPayload => new StarDust\Write\EntryPayload($tenant, $sdModel, $row['fields']), $batch);
        $result   = $engine->bulkWrite($payloads, new StarDust\Write\BulkIngestOptions(chunkSize: count($payloads)));
        $ids      = [];
        foreach ($result->chunks as $chunk) {
            if ($chunk->outcome !== StarDust\Write\BulkChunkResult::OUTCOME_COMMITTED) {
                throw new RuntimeException('StarDust rolled back a chunk while building');
            }
            foreach ($chunk->entryIds as $sdId) {
                $ids[] = (int) $sdId;
            }
        }
        $db->transStart();
        foreach ($batch as $i => $row) {
            $db->query('INSERT INTO hyper_entries (model_id, sd_entry_id, creator_id, editor_id, created_at, updated_at, payload_bytes) VALUES (?, ?, 1, 1, ?, ?, ?)', [$modelId, $ids[$i], $row['at'], $row['at'], $row['bytes']]);
            $entryId = (int) $db->insertID();
            $db->query('INSERT INTO hyper_entry_revisions (entry_id, fields, creator_id, created_at) VALUES (?, NULL, 1, ?)', [$entryId, $row['at']]);
            $db->query('UPDATE hyper_entries SET current_revision_id = ? WHERE id = ?', [(int) $db->insertID(), $entryId]);
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new RuntimeException('Recording the entries failed');
        }
        $done += count($batch);
        $batch = [];
        $bytes = 0;
        echo sprintf("  %d of %d entries, %.0f s\n", $done, $entries, microtime(true) - $start);
    };

    for ($i = 0; $i < $entries; $i++) {
        $isLarge = ($i % 100) < $large;
        $title   = ucfirst($sentence(mt_rand(2, 5))) . ' ' . $i;
        if (mt_rand(1, 100) <= 8) {
            $title .= ' ' . TERM;
        }
        if ($isLarge) {
            $size = mt_rand(1048576, 3145728);
            $body = '<p>' . $sentence(20) . '</p>' . mb_strcut(str_repeat($block, (int) ceil($size / strlen($block))), 0, $size, 'UTF-8');
        } else {
            $body = '<p>' . $sentence(mt_rand(40, 160)) . '</p><p style="x">' . $sentence(20) . '</p>';
        }
        $fields = ['title' => $title, 'body' => $body, 'views' => (string) mt_rand(0, 100000)];
        $size   = strlen(App\Libraries\Upgrade\Payload::encode((object) $fields));

        // A batch is cut at 1,000 entries or about 6 MB, so no write is larger than the server takes.
        if ($batch !== [] && (count($batch) >= 1000 || $bytes + $size > 6 * 1048576)) {
            $flush();
        }
        $batch[] = ['fields' => $fields, 'bytes' => $size, 'at' => date('Y-m-d H:i:s', $from + mt_rand(0, 365 * 86400))];
        $bytes  += $size;
    }
    $flush();

    // A write version for the saved lists that tests nothing, and the counts, so the next run starts clean.
    (new App\Data\WriteVersions())->bump([$modelId]);
    $count = $db->query('SELECT COUNT(*) AS n, SUM(payload_bytes) AS b FROM hyper_entries WHERE model_id = ?', [$modelId])->getRowArray();
    echo sprintf("built %s as model %d, %d entries, %.1f MB of fields, %.0f s\n", $label, $modelId, $count['n'], $count['b'] / 1048576, microtime(true) - $start);
}

/**
 * An engine on a connection of its own, and the CodeIgniter connection, both with the binary log off for the session,
 * so a build of gigabytes of bodies is not written to the server's binary log a second time. A server that refuses
 * the setting (the account lacks the privilege) just logs as usual.
 */
function writeEngine(CodeIgniter\Database\BaseConnection $db): StarDust\StarDust
{
    try {
        $db->query('SET sql_log_bin = 0');
    } catch (Throwable) {
        echo "the binary log stays on for this build
";
    }
    $group = config('Database')->{config('Database')->defaultGroup};
    $dsn   = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $group['hostname'], (int) ($group['port'] ?? 3306), $group['database']);
    $pdo   = new PDO($dsn, (string) $group['username'], (string) $group['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_log_bin = 0',
    ]);
    $engine = config('HyperEngine');

    return new StarDust\StarDust(new StarDust\Config\Config(
        pdo: $pdo,
        logger: service('logger'),
        artifactDir: WRITEPATH . 'stardust' . DIRECTORY_SEPARATOR . 'artifacts',
        pidFileDir: WRITEPATH . 'stardust' . DIRECTORY_SEPARATOR . 'pid',
        tickBudgetSeconds: $engine->tickBudgetSeconds,
        tablePrefix: $engine->tablePrefix,
    ));
}

function dropDataset(string $label): void
{
    $model = datasetModel($label);
    if ($label === '' || ! str_starts_with($label, 'measure ') || $model === null) {
        echo "no generated model named '$label'\n";

        return;
    }
    $db = db_connect();
    $db->query('DELETE r FROM hyper_entry_revisions r JOIN hyper_entries e ON e.id = r.entry_id WHERE e.model_id = ?', [$model['id']]);
    $db->query('DELETE FROM hyper_entries WHERE model_id = ?', [$model['id']]);
    $db->query('DELETE FROM hyper_model_revisions WHERE model_id = ?', [$model['id']]);
    $db->query('DELETE FROM hyper_models WHERE id = ?', [$model['id']]);
    echo "removed $label from the hyper tables. Its rows in sd_entry_data go with the database.\n";
}

/** One request in this process, printed as a line of JSON. */
function one(array $options, int $bootPeak): void
{
    $model = datasetModel((string) $options['label']);
    if ($model === null) {
        fwrite(STDERR, "No such dataset\n");
        exit(2);
    }
    if (($options['cold'] ?? '0') === '1') {
        service('cache')->clean();
    }
    $kind   = (string) $options['kind'];
    $params = ['draw' => 1, 'id' => $model['id'], 'start' => (int) ($options['start'] ?? 0), 'length' => 10];
    if ($kind === 'search') {
        $params['search'] = ['value' => TERM];
    }
    if ($kind === 'sort') {
        $params['order']   = [['column' => 0, 'dir' => 'asc']];
        $params['columns'] = [['data' => 'title']];
    }
    if ($kind === 'fixed') {
        $params['order']   = [['column' => 0, 'dir' => 'asc']];
        $params['columns'] = [['data' => 'date_modified']];
    }

    $before = memory_get_peak_usage();
    $t      = hrtime(true);
    $result = service('entryStore')->modelDataTable($params);
    $ms     = (hrtime(true) - $t) / 1e6;

    echo json_encode([
        'kind'     => $kind,
        'cold'     => ($options['cold'] ?? '0') === '1',
        'start'    => $params['start'],
        'ms'       => round($ms),
        'peak_mb'  => round(memory_get_peak_usage() / 1048576, 1),
        'boot_mb'  => round($before / 1048576, 1),
        'total'    => $result['recordsTotal'],
        'filtered' => $result['recordsFiltered'],
        'rows'     => count($result['data']),
        'first_id' => $result['data'][0]['id'] ?? null,
    ]) . PHP_EOL;
}

/** Run the requests of every dataset, each in a process of its own. */
function run(string $repo, string $database, array $options): void
{
    $memory = $options['memory'] ?? '128M';
    $labels = isset($options['labels']) ? explode(',', $options['labels']) : null;

    // The datasets are the models whose name starts with `measure `.
    $list = [];
    foreach (labelsOf($repo, $database) as $name) {
        if ($labels === null || in_array($name, $labels, true)) {
            $list[] = $name;
        }
    }

    $php  = escapeshellarg(PHP_BINARY);
    $self = escapeshellarg(__FILE__);
    $results = [];
    $call = function (string $label, string $kind, bool $cold, int $start) use ($php, $self, $repo, $database, $memory, &$results): array {
        $cmd = "$php -d memory_limit=$memory $self one " . escapeshellarg($repo) . ' ' . escapeshellarg($database)
            . ' ' . escapeshellarg('--label=' . $label) . " --kind=$kind --cold=" . ($cold ? 1 : 0) . " --start=$start";
        $text = shell_exec($cmd . ' 2>&1') ?? '';
        $line = trim((string) strrchr("\n" . trim($text), "\n"));
        $row  = json_decode($line, true);
        if (! is_array($row)) {
            $row = ['kind' => $kind, 'cold' => $cold, 'start' => $start, 'error' => substr(trim($text), -300)];
        }
        $row['label'] = $label;
        $results[]    = $row;
        echo json_encode($row) . PHP_EOL;

        return $row;
    };

    foreach ($list as $label) {
        echo "== $label (PHP at $memory)\n";
        $call($label, 'fixed', true, 0);
        foreach (['search', 'sort'] as $kind) {
            $first = $call($label, $kind, true, 0);
            if (isset($first['error'])) {
                continue;
            }
            $middle = max(10, (int) floor(($first['filtered'] / 10) / 2) * 10);
            $call($label, $kind, false, 0);
            $call($label, $kind, false, $middle);
            $call($label, $kind, true, $middle);
        }
    }

    if (isset($options['out'])) {
        file_put_contents($options['out'], json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL);
    }
}

/** The names of the generated models, read straight from the database. */
function labelsOf(string $repo, string $database): array
{
    $env = dbSettings($repo . '/.env');
    $env['database'] = $database;
    $pdo = connect($env, true);

    return $pdo->query("SELECT name FROM hyper_models WHERE name LIKE 'measure n%' AND deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
}
