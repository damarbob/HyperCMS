<?php

use App\Data\Entries\EntryLists;
use App\Data\Entries\EntryPayloads;
use App\Data\Entries\EntryRows;
use App\Data\Entries\EntryWriter;
use App\Data\Entries\FieldFilters;
use App\Data\Entries\IndexedReads;
use App\Data\Entries\SavedLists;
use App\Data\Entries\ScanLimitExceeded;
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
    private const DEFINITION = '[{"id":"title","type":"text","filterable":true},{"id":"views","type":"number","filterable":true},{"id":"when","type":"datetime","filterable":true},{"id":"body","type":"longtext"}]';

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
        $modelId = $this->models->create(['name' => 'Plain', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime"}]'], 1);

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
                    ? $this->models->create(['name' => 'Refused', 'fields' => '[{"id":"body","type":"longtext","filterable":true}]'], 1)
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

        $this->models->update($modelId, ['name' => 'Switch', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime"},{"id":"views","type":"number","filterable":true},{"id":"body","type":"longtext"}]'], 1);

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
        $modelId = $this->models->create(['name' => 'Restore', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime"}]'], 1);
        $id      = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Old","when":"2025-03-20T10:30"}'], 1);
        $this->assertSame('2025-03-20T10:30', $this->stored($id)['when'], 'Before the flag the value is kept as it was written.');
        $this->writer->trash([$id], 1);

        $this->models->update($modelId, ['name' => 'Restore', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime","filterable":true}]'], 1);
        $this->indexUntilDone($modelId);
        $this->writer->restore([$id]);

        $this->assertNotNull(self::$conn->table('hyper_entries')->where('id', $id)->get()->getRow()->sd_entry_id, 'StarDust took the restored entry.');
        $this->assertSame('2025-03-20 10:30:00', $this->stored($id)['when']);
    }

    public function testEntriesFromBeforeTheFlagAreCleanedInPlaceSoTheIndexHoldsTheirDates(): void
    {
        $modelId = $this->models->create(['name' => 'Backfill', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime"}]'], 1);
        $late    = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Late","when":"2025-03-20T10:30"}'], 1);
        $early   = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Early","when":"2024-01-05T09:30"}'], 1);
        $blank   = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Blank","when":""}'], 1);
        $odd     = $this->writer->create(['model_id' => $modelId, 'fields' => '{"title":"Odd","when":"next week"}'], 1);

        $this->models->update($modelId, ['name' => 'Backfill', 'fields' => '[{"id":"title","type":"text"},{"id":"when","type":"datetime","filterable":true}]'], 1);
        $this->indexUntilDone($modelId);
        [$sdLate, $sdEarly, $sdBlank, $sdOdd] = $this->sdIds([$late, $early, $blank, $odd]);

        // Flagging the field cleaned the values before the backfill, so the index already holds them.
        $this->assertSame([$sdBlank, $sdOdd, $sdEarly, $sdLate], $this->readIds($modelId, SortSpec::byField('when')), 'Entries with no date first, then by date.');
        $this->assertSame('2025-03-20 10:30:00', $this->stored($late)['when']);

        $report = $this->writer->normaliseModel($modelId);

        $this->assertSame(['checked' => 3, 'rewritten' => 0], ['checked' => $report['checked'], 'rewritten' => $report['rewritten']], 'The command finds nothing left to clean.');
        $this->assertSame([$odd], array_keys($report['failed']), 'A value that is not a date is left as it is and reported.');
        $this->assertSame('2024-01-05 09:30:00', $this->stored($early)['when']);
        $this->assertNull($this->stored($blank)['when']);
        $this->assertSame('next week', $this->stored($odd)['when']);
        $this->assertSame(1, self::$conn->table('hyper_entry_revisions')->where('entry_id', $early)->countAllResults(), 'No version is added.');
        $this->assertSame(0, $this->writer->normaliseModel($modelId)['rewritten'], 'A second run changes nothing.');
    }

    public function testAnIndexedReadStopsAtTheScanLimitInsteadOfWalkingTheModel(): void
    {
        $modelId = $this->models->create(['name' => 'Bounded', 'fields' => '[{"id":"views","type":"number","filterable":true}]'], 1);
        $this->indexUntilDone($modelId);
        foreach (['1', '2', '3', '4', '5'] as $views) {
            $this->writer->create(['model_id' => $modelId, 'fields' => json_encode(['views' => $views])], 1);
        }
        $reads  = new IndexedReads(self::$stardust, self::$conn);
        $config = config('HyperEngine');
        $before = $config->scanLimit;
        $sort   = SortSpec::byField('views');

        try {
            $config->scanLimit = 3;

            $this->assertCount(3, $reads->page($modelId, $sort, null, 0, 3)['ids'], 'A page that stays within the limit is read.');
            $this->assertCount(1, $reads->page($modelId, $sort, null, 2, 1)['ids']);

            foreach ([[2, 2], [1_000_000_000, 10], [0, null]] as [$offset, $limit]) {
                try {
                    $reads->page($modelId, $sort, null, $offset, $limit);
                    $this->fail("Offset $offset and limit " . var_export($limit, true) . ' should be refused.');
                } catch (ScanLimitExceeded $e) {
                    $this->assertStringContainsString('3', $e->getMessage());
                    $this->assertStringNotContainsString('5', $e->getMessage(), 'The message leaves out how many entries there are.');
                }
            }

            $config->scanLimit = 0;
            $this->assertCount(5, $reads->page($modelId, $sort, null, 0, null)['ids'], 'A limit of 0 means no limit.');
        } finally {
            $config->scanLimit = $before;
        }
    }

    public function testADeepOffsetIsReachedByWalkingAndTheSameWalkIsRepeatableFromItsCursors(): void
    {
        $modelId = $this->models->create(['name' => 'Walked', 'fields' => '[{"id":"views","type":"number","filterable":true}]'], 1);
        $this->indexUntilDone($modelId);
        $sdModel = (int) self::$conn->table('hyper_models')->where('id', $modelId)->get()->getRow()->sd_model_id;

        // 250 entries whose numbers are shuffled, written straight to StarDust because only its ids are read.
        $views = range(0, 249);
        mt_srand(7);
        shuffle($views);
        $byViews = [];
        foreach ($views as $number) {
            $byViews[$number] = self::$stardust->write(new \StarDust\Write\EntryPayload(1, $sdModel, ['views' => (string) $number]))->entryId;
        }
        ksort($byViews);
        $expected = array_values($byViews);

        $reads = new IndexedReads(self::$stardust, self::$conn, new WriteVersions(self::$conn));
        $sort  = SortSpec::byField('views');

        // The first run walks and keeps cursors, the second starts from them, and both give the sorted ids.
        foreach ([1, 2] as $run) {
            foreach ([[0, 10], [95, 10], [100, 100], [150, 20], [240, 20], [0, null], [200, null]] as [$offset, $limit]) {
                $page = $reads->page($modelId, $sort, null, $offset, $limit);

                $this->assertSame(array_slice($expected, $offset, $limit), $page['ids'], "run $run, offset $offset, limit " . var_export($limit, true));
                $this->assertSame($limit !== null && $offset + $limit < 250, $page['more'], "more at offset $offset");
            }
        }

        $this->assertSame(250, $reads->count($modelId, null, 1000));
        $this->assertNull($reads->count($modelId, null, 120), 'A count past its cap has no total.');
    }

    public function testAModelWithAFieldStillBeingIndexedStaysInTheTrashWithAMessage(): void
    {
        $modelId = $this->models->create(['name' => 'Busy', 'fields' => '[{"id":"when","type":"datetime"}]'], 1);
        $this->writer->create(['model_id' => $modelId, 'fields' => '{"when":"2025-03-20T10:30"}'], 1);

        // The model has an entry, so promoting leaves a backfill running until a tick has finished it.
        $this->models->update($modelId, ['name' => 'Busy', 'fields' => '[{"id":"when","type":"datetime","filterable":true}]'], 1);
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
            $this->models->update($modelId, ['name' => 'Failing', 'fields' => '[{"id":"views","type":"datetime","filterable":true}]'], 1);
            $this->fail('StarDust should refuse a second retype of the field.');
        } catch (IndexingFailed $e) {
            $this->assertStringContainsString('views', $e->getMessage());
        }
        $this->assertStringContainsString('datetime', self::$conn->table('hyper_models')->where('id', $modelId)->get()->getRow()->fields, 'The model itself was saved.');
        $this->indexUntilDone($modelId);
    }

    public function testAListSortedOnAnIndexedFieldReadsThroughTheIndexAndPagesLikeTheSavedList(): void
    {
        $lists = $this->lists();
        // The same entries in a model whose field is not filterable, which the saved list sorts in PHP.
        $flagged = $this->models->create(['name' => 'Indexed list', 'fields' => '[{"id":"title","type":"text"},{"id":"views","type":"number","filterable":true}]'], 1);
        $plain   = $this->models->create(['name' => 'Saved list', 'fields' => '[{"id":"title","type":"text"},{"id":"views","type":"number"}]'], 1);
        $this->indexUntilDone($flagged);
        foreach ([['A', '30'], ['B', '5'], ['C', ''], ['D', '12.5'], ['E', '100']] as [$title, $views]) {
            foreach ([$flagged, $plain] as $model) {
                $this->writer->create(['model_id' => $model, 'fields' => json_encode(['title' => $title, 'views' => $views])], 1);
            }
        }

        $request = static fn (int $model, string $dir, int $start, int $length): array => [
            'id' => $model, 'draw' => 3, 'start' => $start, 'length' => $length,
            'order' => [['column' => 0, 'dir' => $dir]], 'columns' => [['data' => 'views']],
        ];

        foreach (['desc' => ['E', 'A', 'D', 'B', 'C'], 'asc' => ['C', 'B', 'D', 'A', 'E']] as $dir => $expected) {
            foreach ([0, 2, 4] as $start) {
                $indexed = $lists->modelDataTable($request($flagged, $dir, $start, 2));
                $saved   = $lists->modelDataTable($request($plain, $dir, $start, 2));

                $this->assertSame(array_slice($expected, $start, 2), array_map(static fn (array $row): string => json_decode($row['fields'] ?? '{}', true)['title'] ?? $row['title'], $indexed['data']), "$dir from $start");
                $this->assertSame(array_column($saved['data'], 'title'), array_column($indexed['data'], 'title'), 'The same page as the saved list.');
                $this->assertSame($saved['recordsTotal'], $indexed['recordsTotal']);
                $this->assertSame($saved['recordsFiltered'], $indexed['recordsFiltered']);
                $this->assertSame(array_keys($saved['data'][0]), array_keys($indexed['data'][0]), 'The same row shape.');
            }
        }

        $all = $lists->modelDataTable($request($flagged, 'desc', 0, -1));
        $this->assertSame(['E', 'A', 'D', 'B', 'C'], array_column($all['data'], 'title'), 'A length of -1 lists every entry.');
        $this->assertSame(5, $all['recordsTotal']);
        $this->assertSame(3, $all['draw']);
    }

    public function testAnEntryTrashedAfterTheIndexIsLeftOutOfTheIndexedList(): void
    {
        $lists   = $this->lists();
        $modelId = $this->models->create(['name' => 'Trashed from list', 'fields' => '[{"id":"views","type":"number","filterable":true}]'], 1);
        $this->indexUntilDone($modelId);
        $one = $this->writer->create(['model_id' => $modelId, 'fields' => '{"views":"1"}'], 1);
        $this->writer->create(['model_id' => $modelId, 'fields' => '{"views":"2"}'], 1);
        $this->writer->trash([$one], 1);

        $table = $lists->modelDataTable(['id' => $modelId, 'draw' => 1, 'start' => 0, 'length' => 10, 'order' => [['column' => 0, 'dir' => 'asc']], 'columns' => [['data' => 'views']]]);

        $this->assertSame(['2'], array_column($table['data'], 'views'));
        $this->assertSame(1, $table['recordsTotal']);
    }

    public function testTheTokenApiPageFiltersAndSortsOnIndexedFields(): void
    {
        $lists   = $this->lists();
        $modelId = $this->models->create(['name' => 'Api page', 'fields' => '[{"id":"title","type":"text","filterable":true},{"id":"views","type":"number","filterable":true},{"id":"when","type":"datetime","filterable":true}]'], 1);
        $this->indexUntilDone($modelId);
        foreach ([['A', '30', '2025-01-01T10:00'], ['B', '5', '2025-02-01T10:00'], ['C', '', ''], ['D', '12.5', '2025-03-01T10:00'], ['E', '100', '2025-04-01T10:00']] as [$title, $views, $when]) {
            $this->writer->create(['model_id' => $modelId, 'fields' => json_encode(['title' => $title, 'views' => $views, 'when' => $when])], 1);
        }
        $indexed = $lists->indexedFields($modelId);
        $this->assertSame(['title' => 'string', 'views' => 'numeric', 'when' => 'datetime'], $indexed);

        $page = static fn (array $filter, ?SortSpec $sort, int $limit, int $number, int $cap = 100, array $hidden = []): array => $lists->fieldPage($modelId, $hidden, $sort, FieldFilters::parse($filter, $indexed), $limit, $number, $cap);
        $titles = static fn (array $result): array => array_map(static fn (array $row): string => json_decode($row['fields'], true)['title'], $result['data']);

        $result = $page(['views' => ['gte' => '10']], SortSpec::byField('views', SortDirection::Desc), 2, 1);
        $this->assertSame(['E', 'A'], $titles($result));
        $this->assertSame(3, $result['total'], 'The count reads the ids of the matches.');
        $this->assertTrue($result['more']);
        $this->assertSame(array_keys((new EntryRows(self::$conn))->assemble(['id' => 1, 'created_at' => '', 'date_deleted' => null, 'model_id' => 1, 'model_name' => '', 'model_fields' => '', 'user_groups' => '', 'date_modified' => '', 'data_id' => 1, 'created_by' => '', 'edited_by' => '', 'deleted_by' => ''], [])), array_keys($result['data'][0]), 'The same row as every other list.');

        $result = $page(['views' => ['gte' => '10']], SortSpec::byField('views', SortDirection::Desc), 2, 2);
        $this->assertSame(['D'], $titles($result));
        $this->assertFalse($result['more']);

        $this->assertSame(['B'], $titles($page(['views' => '5'], null, 10, 1)), 'An equality on a number.');
        $this->assertSame(['E', 'D'], $titles($page(['when' => ['gte' => '2025-03-01T10:00']], SortSpec::byField('when', SortDirection::Desc), 10, 1)), 'A range on a date, read as UTC.');
        $this->assertSame(['C'], $titles($page(['when' => ['is_null' => '1']], null, 10, 1)));
        $this->assertSame(['A'], $titles($page(['title' => ['prefix' => 'A']], null, 10, 1)));
        $this->assertSame(['E', 'D', 'C', 'B', 'A'], $titles($page([], SortSpec::byId(SortDirection::Desc), 10, 1)), 'No filter lists every entry.');

        $capped = $page(['views' => ['gte' => '1']], null, 2, 1, 2);
        $this->assertNull($capped['total'], 'A match set past the cap has no total.');
        $this->assertTrue($capped['more']);

        $this->assertSame(['data' => [], 'total' => 0, 'more' => false], $page([], null, 10, 1, 100, [$modelId]), 'A hidden model has no entries.');
    }

    private function lists(): EntryLists
    {
        self::$conn->query('CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY, username VARCHAR(100))');
        self::$conn->query("INSERT IGNORE INTO users (id, username) VALUES (1, 'tester')");

        $payloads = new EntryPayloads(self::$stardust);

        return new EntryLists(self::$conn, new EntryRows(self::$conn, $payloads), null, $payloads, new SavedLists(), new WriteVersions(self::$conn), null, new IndexedReads(self::$stardust, self::$conn));
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
