<?php

use App\Data\Entries\EntryWriter;
use App\Data\Entries\StarDustEntries;
use App\Data\IndexingFailed;
use App\Data\ModelStore;
use App\Data\NamedLock;
use App\Data\WriteVersions;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config as DatabaseConfig;
use CodeIgniter\Test\CIUnitTestCase;
use Psr\Log\NullLogger;
use StarDust\Config\Config as StarDustConfig;
use StarDust\Read\EntryQuery;
use StarDust\Read\SortDirection;
use StarDust\Read\SortSpec;
use StarDust\StarDust;

/**
 * Marks fields filterable on a real StarDust engine and writes entries through them: the type mapping reaches
 * StarDust, a value is cleaned before StarDust sees it, a value that cannot be read is refused before anything is
 * written, a field that stops being filterable keeps its values, and a model with a field still being indexed
 * cannot be purged yet.
 *
 * It builds its own throwaway database on the server named by the `database.default.*` settings, runs the
 * migrations and StarDust's bootstrap there, and drops it afterwards. The test is skipped when that server cannot
 * be reached.
 *
 * @internal
 */
final class FilterableModelTest extends CIUnitTestCase
{
    private const DEFINITION = '[{"id":"title","type":"text","filterable":true},{"id":"views","type":"number","filterable":true},{"id":"when","type":"datetime-local","filterable":true},{"id":"body","type":"textarea"}]';

    private static string $name = '';

    /** @var array<string, mixed> */
    private static array $server = [];

    private static ?BaseConnection $conn = null;

    private static ?StarDust $stardust = null;

    private EntryWriter $writer;

    private ModelStore $models;

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
        $forge      = DatabaseConfig::forge(self::$conn);
        $files      = glob(APPPATH . 'Database/Migrations/*.php');
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

        $versions     = new WriteVersions(self::$conn);
        $locks        = new NamedLock(self::$conn, 1);
        $this->writer = new EntryWriter(self::$conn, new StarDustEntries(self::$stardust, 1), $versions, $locks);
        $this->models = new ModelStore(self::$conn, self::$stardust, $versions, $locks, $this->writer);
    }

    public function testAFlaggedFieldIsRegisteredWithItsMappedTypeAndIndexedAfterATick(): void
    {
        $modelId = $this->models->create(['name' => 'Mapped', 'fields' => self::DEFINITION], 1);

        $described = $this->described($modelId);
        $this->assertSame('string', $described->field('title')->declaredType);
        $this->assertSame('numeric', $described->field('views')->declaredType);
        $this->assertSame('datetime', $described->field('when')->declaredType);
        $this->assertFalse($described->field('body')->isFilterable, 'A field without the flag stays as it was.');
        $this->assertSame('string', $described->field('body')->declaredType);

        $this->indexUntilDone($modelId);

        $this->assertSame(['title' => 'indexed', 'views' => 'indexed', 'when' => 'indexed'], $this->models->filterableState($modelId));
    }

    public function testAModelWithoutTheFlagIsRegisteredAsBefore(): void
    {
        $modelId = $this->models->create(['name' => 'Plain', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime-local"}]'], 1);

        $described = $this->described($modelId);
        foreach ($described->fields as $field) {
            $this->assertSame('string', $field->declaredType);
            $this->assertFalse($field->isFilterable);
        }
        $this->assertSame([], $this->models->filterableState($modelId));
    }

    public function testAFlagOnATypeThatCannotTakeItIsRefusedWithAMessage(): void
    {
        foreach (['create', 'update'] as $call) {
            try {
                $call === 'create'
                    ? $this->models->create(['name' => 'Refused', 'fields' => '[{"id":"body","type":"textarea","filterable":true}]'], 1)
                    : $this->models->update($this->models->create(['name' => 'Fine', 'fields' => '[{"id":"title","type":"text"}]'], 1), ['name' => 'Fine', 'fields' => '[{"id":"files","type":"file","filterable":true}]'], 1);
                $this->fail('The flag should have been refused.');
            } catch (InvalidArgumentException $e) {
                $this->assertMatchesRegularExpression('/body|files/', $e->getMessage());
            }
        }

        $this->assertSame(0, self::$conn->table('hyper_models')->where('name', 'Refused')->countAllResults(), 'Nothing is saved for a refused definition.');
    }

    public function testAnEntryIsStoredCleanAndSortsThroughTheIndex(): void
    {
        $modelId = $this->models->create(['name' => 'Sortable', 'fields' => self::DEFINITION], 1);
        $this->indexUntilDone($modelId);

        $late  = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Late","views":"12.50","when":"2025-03-20T10:30","body":"<p>x</p>"}'], 1);
        $early = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Early","views":"","when":"2024-01-05T09:30:15","body":"y"}'], 1);
        $none  = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"None","views":"3","when":"0000-00-00T00:00","body":"z"}'], 1);

        $this->assertEquals(
            ['title' => 'Late', 'views' => '12.50', 'when' => '2025-03-20 10:30:00', 'body' => '<p>x</p>'],
            $this->stored($late),
            'A date gets a space and seconds, a number is kept as written and a field without the flag is untouched.',
        );
        $this->assertNull($this->stored($early)['views'], 'A blank number is null and never 0.');
        $this->assertNull($this->stored($none)['when'], 'The zero date is null.');

        [$sdLate, $sdEarly, $sdNone] = $this->sdIds([$late, $early, $none]);

        // StarDust puts the entry with no date last in a descending sort and first in an ascending one.
        $this->assertSame([$sdLate, $sdEarly, $sdNone], $this->readIds($modelId, SortSpec::byField('when', SortDirection::Desc)));
        $this->assertSame([$sdNone, $sdEarly, $sdLate], $this->readIds($modelId, SortSpec::byField('when')));
        $this->assertSame([$sdEarly, $sdNone, $sdLate], $this->readIds($modelId, SortSpec::byField('views')), 'No number first, then 3, then 12.5.');
    }

    public function testAValueThatCannotBeReadIsRefusedBeforeAnythingIsWritten(): void
    {
        $modelId = $this->models->create(['name' => 'Strict', 'fields' => self::DEFINITION], 1);
        $this->indexUntilDone($modelId);
        $before = self::$conn->table('hyper_entries')->countAllResults();

        foreach (['{"views":"abc"}', '{"when":"20/03/2025"}', '{"title":["a","b"]}'] as $fields) {
            try {
                $this->writer->create(['model_id' => $modelId, 'fields' => $fields], 1);
                $this->fail("$fields should have been refused.");
            } catch (InvalidArgumentException $e) {
                $this->assertMatchesRegularExpression('/views|when|title/', $e->getMessage());
            }
        }
        $this->assertSame($before, self::$conn->table('hyper_entries')->countAllResults(), 'The entry is never half written.');

        // A refused edit leaves the entry as it was, with no new version.
        $id = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Keep","views":"1"}'], 1);
        try {
            $this->writer->update($id, ['fields' => '{"title":"Keep","views":"one"}'], 1);
            $this->fail('The edit should have been refused.');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame(1, self::$conn->table('hyper_entry_revisions')->where('entry_id', $id)->countAllResults());
        $this->assertSame('1', $this->stored($id)['views']);
    }

    public function testTurningTheFlagOffDemotesTheFieldAndKeepsItsValues(): void
    {
        $modelId = $this->models->create(['name' => 'Switch', 'fields' => self::DEFINITION], 1);
        $this->indexUntilDone($modelId);
        $id = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Kept","when":"2025-03-20T10:30"}'], 1);

        $this->models->update($modelId, ['name' => 'Switch', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime-local"},{"id":"views","type":"number","filterable":true},{"id":"body","type":"textarea"}]'], 1);

        $described = $this->described($modelId);
        $this->assertFalse($described->field('title')->isFilterable);
        $this->assertFalse($described->field('when')->isFilterable);
        $this->assertTrue($described->field('views')->isFilterable, 'A field that stays flagged stays indexed.');
        $this->assertSame(['views' => 'indexed'], $this->models->filterableState($modelId));
        $this->assertSame('Kept', $this->stored($id)['title'], 'The value is still in the entry.');
        $this->assertSame('2025-03-20 10:30:00', $this->stored($id)['when']);

        // Turned back on, the field is indexed again from the stored values.
        $this->models->update($modelId, ['name' => 'Switch', 'fields' => self::DEFINITION], 1);
        $this->indexUntilDone($modelId);
        $this->assertSame(['title' => 'indexed', 'views' => 'indexed', 'when' => 'indexed'], $this->models->filterableState($modelId));
    }

    public function testAnEntryTrashedBeforeTheFlagComesBackCleaned(): void
    {
        $modelId = $this->models->create(['name' => 'Restore', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime-local"}]'], 1);
        $id      = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Old","when":"2025-03-20T10:30"}'], 1);
        $this->assertSame('2025-03-20T10:30', $this->stored($id)['when'], 'Before the flag the value is kept as it was written.');
        $this->writer->trash([$id], 1);

        $this->models->update($modelId, ['name' => 'Restore', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime-local","filterable":true}]'], 1);
        $this->indexUntilDone($modelId);
        $this->writer->restore([$id]);

        $this->assertNotNull(self::$conn->table('hyper_entries')->where('id', $id)->get()->getRow()->sd_entry_id, 'StarDust took the restored entry.');
        $this->assertSame('2025-03-20 10:30:00', $this->stored($id)['when']);
    }

    public function testAModelWithAFieldStillBeingIndexedStaysInTheTrashWithAMessage(): void
    {
        $modelId = $this->models->create(['name' => 'Busy', 'fields' => '[{"id":"when","type":"datetime-local"}]'], 1);
        $this->writer->create(['model_id' => $modelId, 'fields' => '{"when":"2025-03-20T10:30"}'], 1);

        // The model has an entry, so promoting leaves a backfill running until a tick has finished it.
        $this->models->update($modelId, ['name' => 'Busy', 'fields' => '[{"id":"when","type":"datetime-local","filterable":true}]'], 1);
        $this->assertSame(['when' => 'indexing'], $this->models->filterableState($modelId));
        $this->models->trash([$modelId], 1);

        try {
            $this->models->emptyTrash();
            $this->fail('The purge should have been refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('1', $e->getMessage());
        }
        $this->assertSame(1, self::$conn->table('hyper_models')->where('id', $modelId)->countAllResults(), 'The model is still in the trash.');
        $this->assertSame(1, self::$conn->table('hyper_entries')->where('model_id', $modelId)->countAllResults(), 'So are its entries.');

        $this->indexUntilDone($modelId);
        $this->models->emptyTrash();
        $this->assertSame(0, self::$conn->table('hyper_models')->where('id', $modelId)->countAllResults());
    }

    public function testAnIndexingFailureAfterTheSaveIsReportedWithoutLosingTheModel(): void
    {
        $modelId = $this->models->create(['name' => 'Failing', 'fields' => '[{"id":"views","type":"number","filterable":true}]'], 1);
        $this->indexUntilDone($modelId);
        $this->writer->create(['model_id' => $modelId, 'fields' => '{"views":"5"}'], 1);

        // Changing the type of a filterable field starts a retype, and a second change before it ends is refused.
        $this->models->update($modelId, ['name' => 'Failing', 'fields' => '[{"id":"views","type":"text","filterable":true}]'], 1);
        try {
            $this->models->update($modelId, ['name' => 'Failing', 'fields' => '[{"id":"views","type":"datetime-local","filterable":true}]'], 1);
            $this->fail('StarDust should refuse a second retype of the field.');
        } catch (IndexingFailed $e) {
            $this->assertStringContainsString('views', $e->getMessage());
        }
        $this->assertStringContainsString('datetime-local', self::$conn->table('hyper_models')->where('id', $modelId)->get()->getRow()->fields, 'The model itself was saved.');
        $this->indexUntilDone($modelId);
    }

    private function described(int $modelId): \StarDust\Schema\ModelDescription
    {
        $sdId = (int) self::$conn->table('hyper_models')->where('id', $modelId)->get()->getRow()->sd_model_id;

        return self::$stardust->describeModel(1, $sdId);
    }

    /**
     * Run StarDust's background work until every filterable field of the model can be queried.
     */
    private function indexUntilDone(int $modelId): void
    {
        for ($i = 0; $i < 20; $i++) {
            if (! in_array('indexing', $this->models->filterableState($modelId), true)) {
                return;
            }
            self::$stardust->tick(2);
        }

        $this->fail('The fields were not indexed after 20 ticks.');
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(int $entryId): array
    {
        return self::$stardust->get(1, (int) self::$conn->table('hyper_entries')->where('id', $entryId)->get()->getRow()->sd_entry_id)->fields;
    }

    /**
     * @param list<int> $entryIds
     *
     * @return list<int> The StarDust ids.
     */
    private function sdIds(array $entryIds): array
    {
        return array_map(fn (int $id): int => (int) self::$conn->table('hyper_entries')->where('id', $id)->get()->getRow()->sd_entry_id, $entryIds);
    }

    /**
     * @return list<int> The StarDust ids of the model's entries in the order StarDust reads them.
     */
    private function readIds(int $modelId, SortSpec $sort): array
    {
        $sdId = (int) self::$conn->table('hyper_models')->where('id', $modelId)->get()->getRow()->sd_model_id;
        $page = self::$stardust->read(new EntryQuery(1, $sdId, selectFields: [], sort: $sort));

        return array_map(static fn ($entry): int => $entry->id, $page->rows);
    }
}
