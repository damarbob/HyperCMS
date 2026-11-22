<?php

use App\Fields\FieldTypes;
use App\Libraries\Upgrade\Convert\EntryValues;
use App\Libraries\Upgrade\Convert\LabelField;
use App\Libraries\Upgrade\Convert\ModelFields;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The three conversions that run on stored data, tested on arrays.
 *
 * @internal
 */
final class ConvertTest extends CIUnitTestCase
{
    private FieldTypes $types;

    protected function setUp(): void
    {
        parent::setUp();
        $this->types = FieldTypes::core();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function article(): array
    {
        return [
            ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['id' => 'body', 'label' => 'Body', 'type' => 'textarea'],
            ['id' => 'views', 'label' => 'Views', 'type' => 'number'],
            ['id' => 'published_at', 'label' => 'Published', 'type' => 'datetime-local'],
            ['id' => 'tags', 'label' => 'Tags', 'type' => 'checkboxes', 'options' => [['value' => 'news', 'label' => 'News'], ['value' => 'tech', 'label' => 'Tech']]],
            ['id' => 'cover', 'label' => 'Cover', 'type' => 'file'],
        ];
    }

    public function testAModelDefinitionIsConvertedFieldByField(): void
    {
        $result = (new ModelFields($this->types))->convert($this->article());

        $this->assertSame(['title', 'body', 'views', 'published_at', 'tags', 'cover'], array_column($result['fields'], 'id'));
        $this->assertSame(['text', 'longtext', 'number', 'datetime', 'choice', 'file'], array_column($result['fields'], 'type'));
        $this->assertSame([], $result['problems']);
        $this->assertSame([], $result['labels']);
    }

    public function testProblemsAreListedAndTheBadFieldsBecomeText(): void
    {
        $old = [
            ['id' => 'a', 'type' => 'text'],
            ['id' => 'a', 'type' => 'number'],
            ['type' => 'text'],
            'not a field',
            ['id' => 'q', 'label' => 'Q', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'users', 'select' => 'id as value, username as label']]],
            ['id' => 'd', 'type' => 'date'],
        ];

        $result = (new ModelFields($this->types))->convert($old);

        $this->assertSame(['a', 'q', 'd'], array_column($result['fields'], 'id'), 'The repeated id counts once, by its first entry.');
        $this->assertSame(['text', 'text', 'text'], array_column($result['fields'], 'type'));
        $this->assertSame(['#2', 'q', 'd'], array_column($result['problems'], 'field'));
    }

    public function testARelationReportsTheLabelFieldOfItsTarget(): void
    {
        $old = [['id' => 'rel', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'entries', 'select' => 'id as value, {{field:title}} as label', 'where' => "model_name = 'Article'"]]]];

        $result = (new ModelFields($this->types))->convert($old, ['Article' => 1]);

        $this->assertSame('relation', $result['fields'][0]['type']);
        $this->assertSame([['model' => 1, 'field' => 'title', 'from' => 'rel']], $result['labels']);
    }

    public function testTheLabelFieldIsPickedFromAHintThenFromTheFields(): void
    {
        $fields = (new ModelFields($this->types))->convert($this->article())['fields'];

        $this->assertSame('title', LabelField::pick($fields));
        $this->assertSame('views', LabelField::pick($fields, ['nope', 'views']), 'The first hint that names a field wins.');
        $this->assertSame('title', LabelField::pick($fields, ['body']), 'A long text cannot be a label.');

        $this->assertSame('Name', LabelField::pick([['id' => 'x', 'type' => 'text'], ['id' => 'Name', 'type' => 'text']]));
        $this->assertSame('x', LabelField::pick([['id' => 'x', 'type' => 'text'], ['id' => 'y', 'type' => 'text']]));
        $this->assertNull(LabelField::pick([['id' => 'x', 'type' => 'number']]));
        $this->assertNull(LabelField::pick([]));
    }

    public function testEntryValuesTakeTheNewShape(): void
    {
        $fields = (new ModelFields($this->types))->convert($this->article())['fields'];
        $values = new EntryValues($this->types);

        $result = $values->convert(['title' => 'Hello', 'body' => '<p>x</p>', 'views' => '12.50', 'published_at' => '2026-01-05T09:30', 'tags' => 'news', 'cover' => ['uploads/a-1.png'], 'extra' => 'kept'], $fields);

        $this->assertSame(
            ['title' => 'Hello', 'body' => '<p>x</p>', 'views' => 12.5, 'published_at' => '2026-01-05 09:30:00', 'tags' => ['news'], 'cover' => 'uploads/a-1.png', 'extra' => 'kept'],
            $result['fields'],
        );
        $this->assertSame([], $result['problems']);
    }

    public function testEmptyValuesBecomeNullAndMissingKeysAreNotAdded(): void
    {
        $fields = (new ModelFields($this->types))->convert($this->article())['fields'];

        $result = (new EntryValues($this->types))->convert(['views' => '', 'published_at' => '', 'tags' => [], 'cover' => ''], $fields);

        $this->assertSame(['views' => null, 'published_at' => null, 'tags' => [], 'cover' => null], $result['fields']);
    }

    public function testAValueThatDoesNotFitStaysAsItWasAndIsListed(): void
    {
        $fields = (new ModelFields($this->types))->convert($this->article())['fields'];

        $result = (new EntryValues($this->types))->convert(['views' => 'many', 'published_at' => 'soon', 'title' => 'ok'], $fields);

        $this->assertSame(['views' => 'many', 'published_at' => 'soon', 'title' => 'ok'], $result['fields']);
        $this->assertSame(['views', 'published_at'], array_column($result['problems'], 'field'));
    }

    public function testConvertingTwiceChangesNothing(): void
    {
        $fields = (new ModelFields($this->types))->convert($this->article())['fields'];
        $values = new EntryValues($this->types);

        $once = $values->convert(['views' => '10', 'published_at' => '2026-02-02T14:05:00', 'tags' => 'news', 'cover' => ['uploads/a.png'], 'title' => 'T'], $fields)['fields'];
        $twice = $values->convert($once, $fields)['fields'];

        $this->assertSame($once, $twice);
    }
}
