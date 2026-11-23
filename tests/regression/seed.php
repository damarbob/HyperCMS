<?php
declare(strict_types=1);

// Fill an empty, migrated regression database through the app's own services.
// This is how seed.sql was made. It is only needed to build a new dump.
// usage: php seed.php <repoRoot>   (the database comes from the repo's .env)
$repo = rtrim($argv[1], '\\/');
define('FCPATH', $repo . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';

class SeedBoot extends CodeIgniter\Boot
{
    public static function up(Config\Paths $paths): void
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
SeedBoot::up($paths);

$db = db_connect();
if (! $db->tableExists('regression_marker')) {
    echo "Database '{$db->database}' has no regression_marker table, so it is not a regression database. Nothing was seeded.\n";
    exit(1);
}
if ($db->table('models')->countAllResults() > 0) {
    echo "Database '{$db->database}' already holds models, not seeding twice.\n";
    exit(1);
}

$j = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// a superadmin user
$users = auth()->getProvider();
$user = new CodeIgniter\Shield\Entities\User([
    'username' => 'capture_admin',
    'email'    => 'capture_admin@example.test',
    'password' => 'Capture-Admin-2026',
]);
$users->save($user);
$user = $users->findById($users->getInsertID());
$user->activate();
$user->addGroup('superadmin');
$uid = (int) $user->id;
echo "user capture_admin id=$uid\n";

// Two more accounts for the access rules. One is outside the staff groups and one is a developer.
foreach ([['capture_user', 'user', 'Capture-User-2026'], ['capture_dev', 'developer', 'Capture-Dev-2026']] as [$name, $group, $password]) {
    $account = new CodeIgniter\Shield\Entities\User(['username' => $name, 'email' => "$name@example.test", 'password' => $password]);
    $users->save($account);
    $account = $users->findById($users->getInsertID());
    $account->activate();
    $account->addGroup($group);
    echo "user $name id={$account->id}\n";
}

$mm = service('modelStore');
$em = service('entryStore');

$mk = function (string $name, array $fields, array $groups, string $group = 'Content') use ($mm, $uid, $j): int {
    return $mm->create(['name' => $name, 'fields' => $j($fields), 'group' => $group, 'user_groups' => $j($groups), 'icon' => 'file-text'], $uid);
};

$article = $mk('Article', [
    ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
    ['id' => 'body', 'label' => 'Body', 'type' => 'textarea'],
    ['id' => 'views', 'label' => 'Views', 'type' => 'number'],
    ['id' => 'published_at', 'label' => 'Published', 'type' => 'datetime-local'],
    ['id' => 'tags', 'label' => 'Tags', 'type' => 'checkboxes', 'options' => [['value' => 'news', 'label' => 'News'], ['value' => 'tech', 'label' => 'Tech']]],
    ['id' => 'cover', 'label' => 'Cover', 'type' => 'file'],
], []);
$product = $mk('Product', [
    ['id' => 'sku', 'label' => 'SKU', 'type' => 'text'],
    ['id' => 'price', 'label' => 'Price', 'type' => 'number'],
    ['id' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
    ['id' => 'added', 'label' => 'Added', 'type' => 'datetime-local'],
], ['superadmin']);
$linked = $mk('Linked', [
    ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
    ['id' => 'related_model', 'label' => 'Model', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'models', 'select' => 'id as value, name as label']]],
], []);
$scratch = $mk('Scratch', [['id' => 'note', 'label' => 'Note', 'type' => 'text']], []);
$restricted = $mk('Restricted', [['id' => 'secret', 'label' => 'Secret', 'type' => 'text']], ['developer']);
echo "models article=$article product=$product linked=$linked scratch=$scratch restricted=$restricted\n";

// Product is the public model, so a request without a login can read it and nothing else.

$titles = ['Getting started with HyperCMS', 'apple pie recipe', 'Apple pie recipe', 'Éclair for beginners', 'eclair filling',
    '  Leading space title', '<b>Bold</b> headline', 'Zebra crossing', 'Ångström units explained', 'Quarterly report 2026'];
for ($n = 11; $n <= 25; $n++) {
    $titles[] = "Sample post $n";
}
$entryIds = [];
foreach ($titles as $i => $title) {
    $n = $i + 1;
    $body = match (true) {
        $n % 5 === 0 => '<p>Body of post ' . $n . ' with <em>markup</em> and a "quote" and a back\\slash 😀</p>',
        $n % 3 === 0 => 'Plain body ' . $n,
        default      => '<div class="x">Post ' . $n . '</div>',
    };
    $views = in_array($n, [3, 12], true) ? '' : ($n === 7 ? '12.50' : (string) ($n * 10));
    $when = match (true) {
        $n % 4 === 0 => '',
        $n % 2 === 0 => '2026-01-' . str_pad((string) (($n % 28) + 1), 2, '0', STR_PAD_LEFT) . 'T09:30',
        default      => '2026-02-' . str_pad((string) (($n % 28) + 1), 2, '0', STR_PAD_LEFT) . 'T14:05:00',
    };
    $tags = $n % 3 === 0 ? ['news', 'tech'] : ($n % 3 === 1 ? ['news'] : []);
    if ($n >= 21) {
        // the older list-of-objects payload format
        $fields = [['id' => 'title', 'value' => $title], ['id' => 'views', 'value' => $views], ['id' => 'body', 'value' => $body]];
    } else {
        $fields = ['title' => $title, 'body' => $body, 'views' => $views, 'published_at' => $when, 'tags' => $tags, 'cover' => ''];
    }
    $entryIds[$n] = $em->create(['model_id' => $article, 'fields' => $j($fields)], $uid);
}
$productIds = [];
for ($n = 1; $n <= 12; $n++) {
    $productIds[$n] = $em->create(['model_id' => $product, 'fields' => $j([
        'sku' => sprintf('SKU-%03d', $n), 'price' => $n % 4 === 0 ? '' : number_format($n * 3.5, 2, '.', ''),
        'category' => $n % 2 ? 'a' : 'b', 'added' => '2026-03-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT) . 'T08:00',
    ])], $uid);
}
$linkedIds = [];
foreach ([1 => $article, 2 => $product, 3 => $linked] as $n => $mid) {
    $linkedIds[$n] = $em->create(['model_id' => $linked, 'fields' => $j(['title' => "Link $n", 'related_model' => (string) $mid])], $uid);
}
$scratchIds = [$em->create(['model_id' => $scratch, 'fields' => $j(['note' => 'one'])], $uid), $em->create(['model_id' => $scratch, 'fields' => $j(['note' => 'two'])], $uid)];
$restrictedId = $em->create(['model_id' => $restricted, 'fields' => $j(['secret' => 'hidden'])], $uid);

// version history
$em->update($entryIds[1], ['fields' => $j(['title' => 'Getting started with HyperCMS (edited)', 'body' => '<p>v2</p>', 'views' => '15', 'published_at' => '2026-01-05T09:30', 'tags' => ['news'], 'cover' => ''])], $uid);
$em->update($entryIds[1], ['fields' => $j(['title' => 'Getting started with HyperCMS (edited twice)', 'body' => '<p>v3</p>', 'views' => '16', 'published_at' => '2026-01-05T09:30', 'tags' => ['news', 'tech'], 'cover' => ''])], $uid);
$em->update($entryIds[2], ['fields' => $j(['title' => 'apple pie recipe v2', 'body' => 'changed', 'views' => '25', 'published_at' => '', 'tags' => [], 'cover' => ''])], $uid);

// trash
$em->trash([$entryIds[24], $entryIds[25], $productIds[12]], $uid);
$mm->trash([$scratch], $uid);

foreach (['models', 'model_data', 'entries', 'entry_data'] as $t) {
    echo str_pad($t, 12) . ' rows=' . $db->table($t)->countAllResults() . PHP_EOL;
}
echo 'ids ' . $j(['article' => $article, 'product' => $product, 'linked' => $linked, 'scratch' => $scratch, 'restricted' => $restricted, 'firstArticleEntry' => $entryIds[1]]) . PHP_EOL;
