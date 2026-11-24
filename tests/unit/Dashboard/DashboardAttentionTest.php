<?php

use App\Libraries\DashboardAttention;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * What the dashboard points out, and that it points out nothing to a person who could not act on it.
 *
 * @internal
 */
final class DashboardAttentionTest extends CIUnitTestCase
{
    private function models(): array
    {
        return [
            ['id' => 1, 'name' => 'Article', 'label_field' => 'title', 'fields' => '[{"id":"title","type":"text"}]'],
            ['id' => 2, 'name' => 'Product', 'label_field' => null, 'fields' => '[{"id":"sku","type":"text"}]'],
            ['id' => 3, 'name' => 'Empty', 'label_field' => null, 'fields' => '[]'],
        ];
    }

    public function testNothingTrueGivesNoItem(): void
    {
        $models = [$this->models()[0]];

        $this->assertSame([], DashboardAttention::items($models, [], [], true, true));
    }

    public function testAModelWithFieldsAndNoLabelFieldIsListedAndAnEmptyOneIsNot(): void
    {
        $items = DashboardAttention::items($this->models(), [], [], true, false);

        $this->assertCount(1, $items);
        $this->assertSame('warn', $items[0]['tone']);
        $this->assertSame(['Product'], array_column($items[0]['links'], 'label'));
        $this->assertStringContainsString('admin/models/2/edit', $items[0]['links'][0]['href']);
    }

    public function testAFieldStillIndexingIsListedByModel(): void
    {
        $items = DashboardAttention::items([$this->models()[0]], [1 => ['title']], [], true, false);

        $this->assertCount(1, $items);
        $this->assertSame(['Article'], array_column($items[0]['links'], 'label'));
    }

    public function testASkippedModuleIsShownOnlyToWhoManagesSettings(): void
    {
        $skipped = ['Shop' => 'It needs core 0.3.0.'];

        $this->assertSame([], DashboardAttention::items([], [], $skipped, false, false));
        $this->assertSame([], DashboardAttention::items([], [], $skipped, true, false));

        $items = DashboardAttention::items([], [], $skipped, false, true);
        $this->assertCount(1, $items);
        $this->assertSame('bad', $items[0]['tone']);
        $this->assertStringContainsString('Shop', $items[0]['text']);
    }

    public function testModelProblemsAreHiddenFromSomeoneWhoCannotManageModels(): void
    {
        $this->assertSame([], DashboardAttention::items($this->models(), [1 => ['title']], [], false, true));
    }

    public function testManyModelsDoNotFillTheStrip(): void
    {
        $models = [];
        for ($i = 1; $i <= 12; $i++) {
            $models[] = ['id' => $i, 'name' => 'M' . $i, 'label_field' => null, 'fields' => '[{"id":"a","type":"text"}]'];
        }

        $items = DashboardAttention::items($models, [], [], true, false);

        $this->assertCount(1, $items);
        $this->assertCount(4, $items[0]['links']);
        $this->assertStringContainsString('12', $items[0]['text']);
    }
}
