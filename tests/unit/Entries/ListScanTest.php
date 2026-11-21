<?php

use App\Data\Entries\EntryPayloads;
use App\Data\Entries\ListScan;
use App\Data\Entries\SavedLists;
use App\Data\Entries\ScanLimitExceeded;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\HyperEngine;

/**
 * Saved lists on fixed data with accents, blanks and ties.
 *
 * @internal
 */
final class ListScanTest extends CIUnitTestCase
{
    /**
     * @var array<int, array>
     */
    private array $entries = [
        1 => ['title' => 'zebra crossing', 'views' => '10', 'published' => '2026-02-02 08:00:00', 'tags' => ['news']],
        2 => ['title' => 'Éclair au chocolat', 'views' => '', 'published' => '2026-01-05T09:30'],
        3 => ['title' => 'eclair', 'views' => '9.5', 'tags' => ['news', 'tech']],
        4 => ['title' => '  <i>Apple</i> pie', 'views' => 'abc', 'published' => '2025-12-31 23:59:59'],
        5 => [],
        6 => ['title' => 'apple pie', 'views' => '10.00', 'published' => '2026-02-02 08:00:00'],
    ];

    private function candidates(): array
    {
        $rows = [];
        foreach (array_keys($this->entries) as $id) {
            $rows[] = ['id' => $id, 'model_id' => 1, 'sd_entry_id' => 100 + $id, 'payload_bytes' => 10];
        }

        return $rows;
    }

    /**
     * Reads the fixed entries and counts how many were read, so a test can see what was scanned.
     */
    private function reader(int &$read = 0, array $missing = []): Closure
    {
        return function (array $chunk) use (&$read, $missing): array {
            $out = [];
            foreach ($chunk as $row) {
                $read++;
                if (! in_array((int) $row['id'], $missing, true)) {
                    $out[(int) $row['id']] = ['fields' => $this->entries[(int) $row['id']]];
                }
            }

            return $out;
        };
    }

    private function scan(int $limit = 0, int $chunk = 1000): ListScan
    {
        $config               = new HyperEngine();
        $config->scanLimit    = $limit;
        $config->chunkEntries = $chunk;

        return new ListScan(new EntryPayloads(null, null, $config), $config);
    }

    private function ids(array $list): array
    {
        return array_map(static fn (array $row): int => $row[0], $list['rows']);
    }

    public function testSortByTextFieldIgnoresAccentsAndCaseAndTiesGoById(): void
    {
        $asc  = $this->scan()->run($this->candidates(), ['sort' => ['kind' => 'text', 'field' => 'title', 'dir' => 'ASC']], $this->reader());
        $desc = $this->scan()->run($this->candidates(), ['sort' => ['kind' => 'text', 'field' => 'title', 'dir' => 'DESC']], $this->reader());

        // The entry with no title comes first, then apple pie twice (4 and 6), then the two eclairs (3 and 2), then zebra.
        $this->assertSame([5, 4, 6, 3, 2, 1], $this->ids($asc));
        $this->assertSame([1, 2, 3, 4, 6, 5], $this->ids($desc));
        $this->assertSame(6, $asc['total']);
    }

    public function testSortByNumberFieldReadsBlanksAsZero(): void
    {
        $list = $this->scan()->run($this->candidates(), ['sort' => ['kind' => 'number', 'field' => 'views', 'dir' => 'ASC']], $this->reader());

        // No value first, then 0 for the blank and the text (2 and 4), then 9.5, then 10 twice (1 and 6).
        $this->assertSame([5, 2, 4, 3, 1, 6], $this->ids($list));
    }

    public function testSortByDateFieldGivesOnlySpacedDatesAValue(): void
    {
        $list = $this->scan()->run($this->candidates(), ['sort' => ['kind' => 'date', 'field' => 'published', 'dir' => 'ASC']], $this->reader());

        // 2, 3 and 5 have no date the format reads, 4 is the oldest, and 1 and 6 tie.
        $this->assertSame([2, 3, 5, 4, 1, 6], $this->ids($list));
    }

    public function testSortByTheWholeFieldsText(): void
    {
        $list = $this->scan()->run($this->candidates(), ['sort' => ['kind' => 'fields', 'field' => null, 'dir' => 'ASC']], $this->reader());

        // The text of entry 5 is {}, which sorts after {"title", and 4 has leading spaces inside the value.
        $this->assertSame([4, 6, 2, 3, 1, 5], $this->ids($list));
    }

    public function testSearchFindsKeysAndValuesIgnoringAccentsAndKeepsTheCandidateOrder(): void
    {
        $list = $this->scan()->run($this->candidates(), ['search' => 'ECLAIR'], $this->reader());
        $this->assertSame([2, 3], $this->ids($list));

        $byKey = $this->scan()->run($this->candidates(), ['search' => 'tags'], $this->reader());
        $this->assertSame([1, 3], $this->ids($byKey));
    }

    public function testTotalCountsTheFindFilterAndNotTheSearch(): void
    {
        $list = $this->scan()->run($this->candidates(), ['find' => ['field' => 'title', 'value' => 'PIE'], 'search' => 'apple'], $this->reader());

        $this->assertSame([4, 6], $this->ids($list));
        $this->assertSame(2, $list['total']);

        $found = $this->scan()->run($this->candidates(), ['find' => ['field' => 'tags', 'value' => 'news'], 'search' => 'tech'], $this->reader());
        $this->assertSame([3], $this->ids($found));
        $this->assertSame(2, $found['total']);
    }

    public function testEntriesAlreadyMatchedBySqlAreNotRead(): void
    {
        $read = 0;
        $list = $this->scan()->run($this->candidates(), ['search' => 'nothing like this'], $this->reader($read), [1 => true, 2 => true, 3 => true, 4 => true, 5 => true]);

        $this->assertSame([1, 2, 3, 4, 5], $this->ids($list));
        $this->assertSame(1, $read);
    }

    public function testNothingIsReadWhenNoSearchFindOrSortIsAsked(): void
    {
        $read = 0;
        $list = $this->scan()->run($this->candidates(), [], $this->reader($read));

        $this->assertSame([1, 2, 3, 4, 5, 6], $this->ids($list));
        $this->assertSame(0, $read);
        $this->assertSame(6, $list['total']);
    }

    public function testAnEntryStarDustDoesNotReturnIsSkippedAndNotCounted(): void
    {
        $list = $this->scan()->run($this->candidates(), ['sort' => ['kind' => 'number', 'field' => 'views', 'dir' => 'DESC']], $this->reader(missing: [1]));

        $this->assertSame([6, 3, 2, 4, 5], $this->ids($list));
        $this->assertSame(5, $list['total']);
    }

    public function testReadsAreChunkedWithoutChangingTheResult(): void
    {
        $read = 0;
        $list = $this->scan(0, 2)->run($this->candidates(), ['sort' => ['kind' => 'text', 'field' => 'title', 'dir' => 'ASC']], $this->reader($read));

        $this->assertSame([5, 4, 6, 3, 2, 1], $this->ids($list));
        $this->assertSame(6, $read);
    }

    public function testTheByteLimitRefusesALargeScanAndLeavesASmallOneAlone(): void
    {
        $config            = new HyperEngine();
        $config->scanBytes = 59; // The six candidates hold 10 bytes each, 60 in all.
        $scan              = new ListScan(new EntryPayloads(null, null, $config), $config);

        try {
            $scan->run($this->candidates(), ['search' => 'a'], $this->reader());
            $this->fail('The scan should have been refused.');
        } catch (ScanLimitExceeded $e) {
            $this->assertSame([6, 59], [$e->entries, $e->limit]);
            $this->assertStringContainsString('MB', $e->getMessage());
        }

        $config->scanBytes = 60;
        $this->assertCount(6, $scan->run($this->candidates(), [], $this->reader())['rows']);
        $this->assertNotEmpty($scan->run($this->candidates(), ['search' => 'a'], $this->reader())['rows']);
    }

    public function testTheScanLimitRefusesWithAMessageInBothLanguages(): void
    {
        try {
            $this->scan(5)->run($this->candidates(), ['search' => 'a'], $this->reader());
            $this->fail('The scan should have been refused.');
        } catch (ScanLimitExceeded $e) {
            $this->assertSame([6, 5], [$e->entries, $e->limit]);
            $this->assertStringNotContainsString('6', $e->getMessage());
            $this->assertStringContainsString('5', $e->getMessage());
        }

        // The limit counts the entries that must be read, so a list that needs none is never refused.
        $this->assertCount(6, $this->scan(5)->run($this->candidates(), [], $this->reader())['rows']);

        foreach (['en', 'id'] as $locale) {
            $strings = require APPPATH . 'Language/' . $locale . '/EntryLists.php';
            $this->assertArrayHasKey('scanLimit', $strings);
            $this->assertStringNotContainsString('{entries}', $strings['scanLimit']);
            $this->assertStringContainsString('{limit}', $strings['scanLimit']);
        }
    }

    public function testASavedListIsBuiltOnceForOneKeyAndAgainWhenTheVersionChanges(): void
    {
        $lists  = new SavedLists(new MockCache(), new HyperEngine());
        $builds = 0;
        $build  = static function () use (&$builds): array {
            $builds++;

            return ['rows' => [[3, 1], [2, 1]], 'total' => 2];
        };

        $key = ['model', 1, 'apple', ['kind' => 'text', 'field' => 'title', 'dir' => 'ASC'], 57];
        $this->assertSame([[3, 1], [2, 1]], $lists->remember($key, $build)['rows']);
        $lists->remember($key, $build);
        $this->assertSame(1, $builds);

        $key[4] = 58;
        $lists->remember($key, $build);
        $this->assertSame(2, $builds);
    }
}
