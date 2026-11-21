<?php

use App\Data\Entries\EntryPayloads;
use App\Data\Entries\EntryRows;
use CodeIgniter\Test\CIUnitTestCase;
use Config\HyperEngine;

/**
 * @internal
 */
final class EntryPayloadsTest extends CIUnitTestCase
{
    private function config(int $entries, int $bytes): HyperEngine
    {
        $config               = new HyperEngine();
        $config->chunkEntries = $entries;
        $config->chunkBytes   = $bytes;

        return $config;
    }

    private function rows(array $bytes): array
    {
        $rows = [];
        foreach ($bytes as $i => $size) {
            $rows[] = ['id' => $i + 1, 'sd_entry_id' => 100 + $i, 'payload_bytes' => $size];
        }

        return $rows;
    }

    public function testChunksStopAtTheEntryCountAndKeepTheOrder(): void
    {
        $payloads = new EntryPayloads(null, null, $this->config(2, 1000));
        $chunks   = iterator_to_array($payloads->chunks($this->rows([1, 1, 1, 1, 1])), false);

        $this->assertSame([[1, 2], [3, 4], [5]], array_map(static fn (array $c): array => array_column($c, 'id'), $chunks));
    }

    public function testChunksStopAtTheByteCapAndAnOversizeEntryGoesAlone(): void
    {
        $payloads = new EntryPayloads(null, null, $this->config(1000, 100));
        $chunks   = iterator_to_array($payloads->chunks($this->rows([40, 40, 40, 500, 10, 10])), false);

        $this->assertSame([[1, 2], [3], [4], [5, 6]], array_map(static fn (array $c): array => array_column($c, 'id'), $chunks));
    }

    public function testNoChunkHoldsMoreThanAThousandEntries(): void
    {
        $payloads = new EntryPayloads(null, null, $this->config(5000, PHP_INT_MAX));
        $sizes    = array_map(static fn (array $c): int => count($c), iterator_to_array($payloads->chunks($this->rows(array_fill(0, 2500, 1))), false));

        $this->assertSame([1000, 1000, 500], $sizes);
    }

    public function testReadSkipsAnEntryStarDustDoesNotReturn(): void
    {
        $calls    = [];
        $payloads = new EntryPayloads(null, static function (array $ids) use (&$calls): array {
            $calls[] = $ids;

            return [100 => (object) ['fields' => ['title' => 'a']], 102 => (object) ['fields' => []]];
        }, $this->config(1000, 1000));

        $fields = $payloads->fetch($this->rows([1, 1, 1]));

        $this->assertSame([[100, 101, 102]], $calls);
        $this->assertSame([1 => ['title' => 'a'], 3 => []], $fields);
    }

    public function testRowsAreAssembledWithTheOldKeysAndABlankPayloadReadsAsBraces(): void
    {
        $entry = [
            'id' => '1', 'created_at' => '2026-11-14 11:13:35', 'date_deleted' => null, 'model_id' => '1', 'model_name' => 'Article',
            'model_fields' => '[]', 'user_groups' => '[]', 'date_modified' => '2026-11-14 11:13:38', 'data_id' => '45',
            'created_by' => 'ann', 'edited_by' => null, 'deleted_by' => null, 'sd_entry_id' => '9', 'payload_bytes' => '2',
        ];
        $rows = new EntryRows();

        $this->assertSame(
            ['id', 'created_at', 'date_deleted', 'model_id', 'model_name', 'model_fields', 'user_groups', 'fields', 'date_modified', 'data_id', 'created_by', 'edited_by', 'deleted_by'],
            array_keys($rows->assemble($entry, ['title' => 'x'])),
        );
        $this->assertSame('{}', $rows->assemble($entry, [])['fields']);
        $this->assertSame('{"title":"Judul / 日本語"}', $rows->assemble($entry, ['title' => 'Judul / 日本語'])['fields']);
        // A trashed entry keeps the snapshot text its query selected.
        $this->assertSame('{"t":"x"}', $rows->assemble($entry + ['fields' => '{"t":"x"}'], null)['fields']);
    }
}
