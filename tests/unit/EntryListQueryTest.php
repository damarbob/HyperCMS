<?php

use App\Data\EntryStore;
use App\Fields\FieldTypes;
use App\Libraries\EntryListQuery;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * How the query string of a model's list is read into a request that is safe to use.
 *
 * @internal
 */
final class EntryListQueryTest extends CIUnitTestCase
{
    private EntryListQuery $query;

    /** @var list<array<string, mixed>> */
    private array $fields;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query  = new EntryListQuery($this->createMock(EntryStore::class), FieldTypes::core());
        $this->fields = [
            ['id' => 'title', 'type' => 'text', 'filterable' => true],
            ['id' => 'views', 'type' => 'number', 'filterable' => true],
            ['id' => 'published', 'type' => 'datetime', 'filterable' => true],
            ['id' => 'tags', 'type' => 'choice', 'multiple' => true, 'filterable' => false],
            ['id' => 'kind', 'type' => 'choice', 'multiple' => false, 'filterable' => true, 'choices' => [['value' => 'a', 'label' => 'A']]],
            ['id' => 'body', 'type' => 'longtext'],
            ['id' => 'plain', 'type' => 'text'],
        ];
    }

    public function testNothingAskedMeansTheNewestEditFirstOnThePageOne(): void
    {
        $request = $this->query->parse([], $this->fields);

        $this->assertSame(['q' => '', 'sort' => '', 'dir' => 'desc', 'page' => 1, 'per' => 25, 'trash' => false, 'filter' => []], $request);
    }

    public function testASortIsKeptOnlyForAColumnOrAFieldThatCanBeSorted(): void
    {
        $this->assertSame('title', $this->query->parse(['sort' => 'title'], $this->fields)['sort']);
        $this->assertSame('asc', $this->query->parse(['sort' => 'title'], $this->fields)['dir'], 'A field sorts from the start by default.');
        $this->assertSame('desc', $this->query->parse(['sort' => 'views', 'dir' => 'desc'], $this->fields)['dir']);
        $this->assertSame('date_modified', $this->query->parse(['sort' => 'date_modified'], $this->fields)['sort']);
        $this->assertSame('desc', $this->query->parse(['sort' => 'date_modified'], $this->fields)['dir']);

        foreach (['body', 'tags', 'nope', 'id; DROP TABLE', 'created_by'] as $bad) {
            $this->assertSame('', $this->query->parse(['sort' => $bad], $this->fields)['sort'], $bad);
        }
        $this->assertSame('asc', $this->query->parse(['sort' => 'title', 'dir' => 'sideways'], $this->fields)['dir'], 'An odd direction falls back to the default.');
    }

    public function testThePageAndTheRowsPerPageAreHeldToSaneValues(): void
    {
        $this->assertSame(1, $this->query->parse(['page' => '-4'], $this->fields)['page']);
        $this->assertSame(1, $this->query->parse(['page' => 'x'], $this->fields)['page']);
        $this->assertSame(7, $this->query->parse(['page' => '7'], $this->fields)['page']);

        $this->assertSame(50, $this->query->parse(['per' => '50'], $this->fields)['per']);
        $this->assertSame(25, $this->query->parse(['per' => '5000'], $this->fields)['per']);
        $this->assertSame(100, $this->query->parse([], $this->fields, 100)['per'], 'The size the user chose is the default.');
        $this->assertSame(25, $this->query->parse([], $this->fields, 33)['per']);
    }

    public function testTheSearchIsTrimmedAndCut(): void
    {
        $this->assertSame('R&D', $this->query->parse(['q' => '  R&D  '], $this->fields)['q'], 'The text is not escaped before it is compared.');
        $this->assertSame(200, mb_strlen($this->query->parse(['q' => str_repeat('a', 500)], $this->fields)['q']));
        $this->assertSame('', $this->query->parse(['q' => ['a']], $this->fields)['q']);
    }

    public function testTheTrashViewIsOnlyAskedForOnPurpose(): void
    {
        $this->assertTrue($this->query->parse(['trash' => '1'], $this->fields)['trash']);
        $this->assertFalse($this->query->parse(['trash' => '0'], $this->fields)['trash']);
        $this->assertFalse($this->query->parse(['trash' => 'maybe'], $this->fields)['trash']);
    }

    public function testFiltersAreKeptForFilterableFieldsWithAnOperatorTheirTypeOffers(): void
    {
        $request = $this->query->parse(['f' => [
            'title'     => ['prefix' => ' ap ', 'gte' => '1', 'eq' => ''],
            'views'     => ['gte' => '10', 'lte' => '20', 'prefix' => 'x'],
            'published' => ['gte' => '2026-01-01T00:00'],
            'kind'      => ['eq' => 'a'],
            'tags'      => ['eq' => 'a'],
            'plain'     => ['eq' => 'a'],
            'body'      => ['eq' => 'a'],
            'nope'      => ['eq' => 'a'],
        ]], $this->fields);

        $this->assertSame([
            'title'     => ['prefix' => 'ap'],
            'views'     => ['gte' => '10', 'lte' => '20'],
            'published' => ['gte' => '2026-01-01T00:00'],
            'kind'      => ['eq' => 'a'],
        ], $request['filter']);
    }

    public function testOnlySortableTypesAreOffered(): void
    {
        $sortable = fn (array $field): bool => $this->query->sortable($field);

        $this->assertTrue($sortable(['type' => 'text']));
        $this->assertTrue($sortable(['type' => 'number']));
        $this->assertTrue($sortable(['type' => 'datetime']));
        $this->assertTrue($sortable(['type' => 'choice', 'multiple' => false]));
        $this->assertFalse($sortable(['type' => 'choice', 'multiple' => true]));
        foreach (['longtext', 'richtext', 'code', 'boolean', 'file', 'relation', 'hidden'] as $type) {
            $this->assertFalse($sortable(['type' => $type]), $type);
        }
    }
}
