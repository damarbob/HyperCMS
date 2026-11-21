<?php

use App\Data\Entries\EntryEngine;
use App\Data\Entries\EntryRepair;
use App\Data\Entries\EntryWriter;
use App\Data\Entries\StarDustEntries;
use App\Data\ModelStore;
use App\Data\NamedLock;
use App\Data\WriteVersions;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config as DatabaseConfig;
use CodeIgniter\Test\CIUnitTestCase;
use Psr\Log\NullLogger;
use StarDust\Config\Config as StarDustConfig;
use StarDust\StarDust;

/**
 * Proves that a write which dies at step 2, when StarDust is called, leaves HyperCMS rows that
 * `hyper:repair` can finish, and that the write versions only rise once StarDust has the change.
 *
 * It builds its own throwaway database on the server named by the `database.default.*` settings, runs the
 * migrations and StarDust's bootstrap there, and drops it afterwards, so no real data is touched. The test is
 * skipped when that server cannot be reached.
 *
 * @internal
 */
final class EntryWriteStepsTest extends CIUnitTestCase
{
    private static string $name = '';

    /** @var array<string, mixed> */
    private static array $server = [];

    private static ?BaseConnection $conn = null;

    private static ?StarDust $stardust = null;

    private FlakyEntries $engine;

    private EntryWriter $writer;

    private EntryRepair $repair;

    private int $modelId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $server = config('Database')->default;
        if (($server['DBDriver'] ?? '') !== 'MySQLi' || ($server['username'] ?? '') === '') {
            self::markTestSkipped('The database.default.* settings do not name a MySQL server.');
        }
        self::$server = $server;
        self::$name   = 'hyper_unit_' . getmypid() . '_' . bin2hex(random_bytes(3));

        try {
            $admin = new mysqli($server['hostname'], $server['username'], (string) $server['password'], '', (int) ($server['port'] ?? 3306));
            $admin->query('CREATE DATABASE `' . self::$name . '` CHARACTER SET utf8mb4');
            $admin->close();
        } catch (Throwable $e) {
            self::markTestSkipped('The MySQL server is not reachable. ' . $e->getMessage());
        }

        self::$conn = DatabaseConfig::connect(array_merge($server, ['database' => self::$name, 'DBPrefix' => '', 'DBDebug' => true]), false);
        $forge    = DatabaseConfig::forge(self::$conn);
        $files = glob(APPPATH . 'Database/Migrations/*.php');
        sort($files);
        foreach ($files as $file) {
            // The file names start with a date, so Composer cannot find the classes by name.
            require_once $file;
            $class = 'App\\Database\\Migrations\\' . substr(basename($file, '.php'), strpos(basename($file), '_') + 1);
            (new $class($forge))->up();
        }

        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::$name;
        mkdir($dir . DIRECTORY_SEPARATOR . 'pid', 0775, true);
        mkdir($dir . DIRECTORY_SEPARATOR . 'artifacts', 0775, true);
        self::$stardust = new StarDust(new StarDustConfig(
            pdo: new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $server['hostname'], (int) ($server['port'] ?? 3306), self::$name),
                $server['username'],
                (string) $server['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
            ),
            logger: new NullLogger(),
            artifactDir: $dir . DIRECTORY_SEPARATOR . 'artifacts',
            pidFileDir: $dir . DIRECTORY_SEPARATOR . 'pid',
            tablePrefix: 'sd_',
        ));
        self::$stardust->bootstrap();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$name !== '') {
            self::$stardust = null;
            self::$conn?->close();
            self::$conn = null;

            $admin = new mysqli(self::$server['hostname'], self::$server['username'], (string) self::$server['password'], '', (int) (self::$server['port'] ?? 3306));
            $admin->query('DROP DATABASE IF EXISTS `' . self::$name . '`');
            $admin->close();

            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::$name;
            @rmdir($dir . DIRECTORY_SEPARATOR . 'pid');
            @rmdir($dir . DIRECTORY_SEPARATOR . 'artifacts');
            @rmdir($dir);
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $tenant       = (int) config('HyperEngine')->tenantId;
        $this->engine = new FlakyEntries(new StarDustEntries(self::$stardust, $tenant));

        $versions     = new WriteVersions(self::$conn);
        $locks        = new NamedLock(self::$conn, 1);
        $models       = new ModelStore(self::$conn, self::$stardust, $versions, $locks);
        $this->writer = new EntryWriter(self::$conn, $this->engine, $versions, $locks, $models);
        $this->repair = new EntryRepair(self::$conn, new EntryWriter(self::$conn, new StarDustEntries(self::$stardust, $tenant), $versions, $locks, $models), $models, $locks);

        // Every test gets a model of its own, so StarDust's model names never clash.
        $this->modelId = $models->create(['name' => 'Article', 'fields' => '[{"id":"title","type":"text"},{"id":"body","type":"textarea"}]'], 1);
    }

    public function testCreateThatFailsAtStepTwoLeavesAHiddenEntryThatRepairWritesToStarDust(): void
    {
        $before = $this->version();
        $this->engine->failing['write'] = 'before';

        try {
            $this->writer->create(['model_id' => $this->modelId, 'fields' => '{"title":"Hello","body":"World"}'], 1);
            $this->fail('The create should have failed.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('engine down', $e->getMessage());
        }

        $entry = self::$conn->table('hyper_entries')->where('model_id', $this->modelId)->get()->getRowArray();
        $this->assertNotNull($entry, 'Step 1 wrote the entry.');
        $this->assertNull($entry['sd_entry_id'], 'A live row with no StarDust id is a create in progress.');
        $this->assertSame('{"title":"Hello","body":"World"}', $this->revisionText($entry['current_revision_id']), 'The payload waits in its version.');
        $this->assertSame($before, $this->version(), 'The write versions rise only after StarDust has the change.');

        $report = $this->repair->run(0);

        $this->assertSame(1, $report['rewritten']);
        $this->assertSame([], $report['failed']);
        $entry = $this->entry((int) $entry['id']);
        $this->assertNotNull($entry['sd_entry_id']);
        $this->assertNull($this->revisionText($entry['current_revision_id']), 'The version stops holding a copy.');
        $this->assertEquals(['title' => 'Hello', 'body' => 'World'], self::$stardust->get(1, (int) $entry['sd_entry_id'])?->fields);
        $this->assertSame($before + 1, $this->version());
    }

    public function testUpdateThatFailsAtStepTwoKeepsTheOldPayloadInStarDustUntilRepair(): void
    {
        $id     = $this->writer->create(['model_id' => $this->modelId, 'fields' => '{"title":"One"}'], 1);
        $before = $this->version();
        $this->engine->failing['update'] = 'before';

        try {
            $this->writer->update($id, ['fields' => '{"title":"Two"}'], 2);
            $this->fail('The update should have failed.');
        } catch (RuntimeException) {
        }

        $entry = $this->entry($id);
        $this->assertSame(['title' => 'One'], self::$stardust->get(1, (int) $entry['sd_entry_id'])?->fields, 'StarDust was not reached.');
        $this->assertSame('{"title":"Two"}', $this->revisionText($entry['current_revision_id']), 'The new payload waits in the newest version.');
        $older = self::$conn->table('hyper_entry_revisions')->where('entry_id', $id)->where('id !=', $entry['current_revision_id'])->get()->getRowArray();
        $this->assertSame('{"title":"One"}', $older['fields'], 'The live payload was copied into the version it belonged to.');
        $this->assertSame($before, $this->version());

        $report = $this->repair->run(0);

        $this->assertSame(1, $report['rewritten']);
        $this->assertSame(['title' => 'Two'], self::$stardust->get(1, (int) $entry['sd_entry_id'])?->fields);
        $this->assertNull($this->revisionText($entry['current_revision_id']));
        $this->assertSame($before + 1, $this->version());
    }

    public function testUpdateThatDiesAfterStarDustHasThePayloadIsOnlyCleared(): void
    {
        $id = $this->writer->create(['model_id' => $this->modelId, 'fields' => '{"title":"One"}'], 1);
        $this->engine->failing['update'] = 'after';

        try {
            $this->writer->update($id, ['fields' => '{"title":"Two"}'], 2);
            $this->fail('The update should have failed.');
        } catch (RuntimeException) {
        }

        $entry = $this->entry($id);
        $this->assertSame(['title' => 'Two'], self::$stardust->get(1, (int) $entry['sd_entry_id'])?->fields);
        $this->assertSame('{"title":"Two"}', $this->revisionText($entry['current_revision_id']));

        $report = $this->repair->run(0);

        $this->assertSame(1, $report['cleared']);
        $this->assertSame(0, $report['rewritten']);
        $this->assertNull($this->revisionText($entry['current_revision_id']));
    }

    public function testTrashThatFailsAtStepTwoLeavesAStarDustRowThatRepairDeletes(): void
    {
        $id     = $this->writer->create(['model_id' => $this->modelId, 'fields' => '{"title":"Gone soon"}'], 1);
        $sdId   = (int) $this->entry($id)['sd_entry_id'];
        $before = $this->version();
        $this->engine->failing['delete'] = 'before';

        try {
            $this->writer->trash([$id], 3);
            $this->fail('The trash should have failed.');
        } catch (RuntimeException) {
        }

        $entry = $this->entry($id);
        $this->assertNotNull($entry['deleted_at'], 'Step 1 trashed the entry.');
        $this->assertSame('{"title":"Gone soon"}', $this->revisionText($entry['current_revision_id']), 'The live payload was copied out for the trash view.');
        $this->assertNotNull(self::$stardust->get(1, $sdId), 'StarDust still has it.');
        $this->assertSame($before, $this->version());

        $report = $this->repair->run(0);

        $this->assertSame(1, $report['deleted']);
        $this->assertNull(self::$stardust->get(1, $sdId));
        $this->assertNull($this->entry($id)['sd_entry_id']);
        $this->assertSame('{"title":"Gone soon"}', $this->revisionText($entry['current_revision_id']), 'The snapshot stays for a restore.');
        $this->assertSame($before + 1, $this->version());
    }

    public function testRestoreThatFailsAtStepTwoLeavesAHiddenEntryThatRepairWritesToStarDust(): void
    {
        $id = $this->writer->create(['model_id' => $this->modelId, 'fields' => '{"title":"Back soon"}'], 1);
        $this->writer->trash([$id], 3);
        $before = $this->version();
        $this->engine->failing['write'] = 'before';

        try {
            $this->writer->restore([$id]);
            $this->fail('The restore should have failed.');
        } catch (RuntimeException) {
        }

        $entry = $this->entry($id);
        $this->assertNull($entry['deleted_at'], 'Step 1 made the entry live.');
        $this->assertNull($entry['sd_entry_id'], 'It has no StarDust id until step 3.');
        $this->assertSame('{"title":"Back soon"}', $this->revisionText($entry['current_revision_id']), 'The snapshot is still there.');
        $this->assertSame($before, $this->version());

        $report = $this->repair->run(0);

        $this->assertSame(1, $report['rewritten']);
        $entry = $this->entry($id);
        $this->assertSame(['title' => 'Back soon'], self::$stardust->get(1, (int) $entry['sd_entry_id'])?->fields);
        $this->assertNull($this->revisionText($entry['current_revision_id']));
        $this->assertSame($before + 1, $this->version());
    }

    public function testRepairLeavesARowAloneWhenASaveHoldsItsLock(): void
    {
        $this->engine->failing['write'] = 'before';

        try {
            $this->writer->create(['model_id' => $this->modelId, 'fields' => '{"title":"Busy"}'], 1);
        } catch (RuntimeException) {
        }
        $id = (int) self::$conn->table('hyper_entries')->where('model_id', $this->modelId)->get()->getRow()->id;

        $other = new mysqli(self::$server['hostname'], self::$server['username'], (string) self::$server['password'], self::$name, (int) (self::$server['port'] ?? 3306));
        $other->query("SELECT GET_LOCK('" . NamedLock::forEntry($id) . "', 5)");

        try {
            $report = $this->repair->run(0);
        } finally {
            $other->close();
        }

        $this->assertSame(1, $report['busy']);
        $this->assertSame(0, $report['rewritten']);
        $this->assertNull($this->entry($id)['sd_entry_id']);

        $this->assertSame(1, $this->repair->run(0)['rewritten'], 'Once the save is gone the next run finishes it.');
    }

    public function testRepairRegistersAModelStarDustDoesNotKnow(): void
    {
        self::$conn->table('hyper_models')->where('id', $this->modelId)->update(['sd_model_id' => null]);

        $report = $this->repair->run(0);

        $this->assertGreaterThanOrEqual(1, $report['registered']);
        $this->assertNotNull(self::$conn->table('hyper_models')->where('id', $this->modelId)->get()->getRow()->sd_model_id);
    }

    public function testTheOlderListFormatIsStoredAsAnObjectAndRevisionsAreKept(): void
    {
        $id = $this->writer->create(['model_id' => $this->modelId, 'fields' => '[{"id":"title","value":"List"},{"id":"body","value":"Format"}]'], 1);

        $this->assertEquals(['title' => 'List', 'body' => 'Format'], self::$stardust->get(1, (int) $this->entry($id)['sd_entry_id'])?->fields);

        $this->writer->update($id, ['fields' => '{"title":"Object"}'], 1);
        $this->writer->update($id, [], 1);

        $this->assertSame([], self::$stardust->get(1, (int) $this->entry($id)['sd_entry_id'])?->fields, 'A blank save is an empty payload.');
        $this->assertSame(3, self::$conn->table('hyper_entry_revisions')->where('entry_id', $id)->countAllResults());
        $this->assertSame(2, $this->writer->clearHistory($id));
        $this->assertSame(1, self::$conn->table('hyper_entry_revisions')->where('entry_id', $id)->countAllResults());
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(int $id): array
    {
        return self::$conn->table('hyper_entries')->where('id', $id)->get()->getRowArray();
    }

    private function revisionText(int|string $id): ?string
    {
        return self::$conn->table('hyper_entry_revisions')->where('id', (int) $id)->get()->getRow()->fields;
    }

    /**
     * The write version of the test's model.
     */
    private function version(): int
    {
        return (int) self::$conn->table('hyper_models')->where('id', $this->modelId)->get()->getRow()->write_version;
    }
}

/**
 * An EntryEngine that works until told to fail. `failing['write'] = 'before'` throws instead of calling
 * StarDust, which is a process dying before step 2. `'after'` calls StarDust and then throws, which is a
 * process dying between step 2 and step 3.
 */
final class FlakyEntries implements EntryEngine
{
    /** @var array<string, string> */
    public array $failing = [];

    public function __construct(private readonly EntryEngine $inner)
    {
    }

    public function write(int $sdModelId, array $fields): int
    {
        return $this->through('write', fn (): int => $this->inner->write($sdModelId, $fields));
    }

    public function update(int $sdEntryId, array $fields): void
    {
        $this->through('update', fn () => $this->inner->update($sdEntryId, $fields));
    }

    public function get(int $sdEntryId): ?array
    {
        return $this->through('get', fn (): ?array => $this->inner->get($sdEntryId));
    }

    public function delete(int $sdEntryId): bool
    {
        return $this->through('delete', fn (): bool => $this->inner->delete($sdEntryId));
    }

    private function through(string $call, callable $run): mixed
    {
        if (($this->failing[$call] ?? null) === 'before') {
            throw new RuntimeException("engine down before $call");
        }
        $result = $run();
        if (($this->failing[$call] ?? null) === 'after') {
            throw new RuntimeException("engine down after $call");
        }

        return $result;
    }
}
