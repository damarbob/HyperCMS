<?php

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use Voltic\Libraries\Actions;

/**
 * How an action that an AI wrote is read, before anything is checked or run. The reading is the one place that decides what
 * runs, so it must accept what the prompt asks for and the older shapes, and refuse the rest.
 *
 * @internal
 */
final class ActionsTest extends CIUnitTestCase
{
    private Actions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        service('autoloader')->addNamespace('Voltic', ROOTPATH . 'modules/Voltic');
        $this->actions = new Actions(new User());
    }

    public function testAModelIsReadFromItsName(): void
    {
        $action = $this->actions->normalise(['type' => 'create_model', 'params' => ['name' => '  Books ', 'fields' => [['id' => 'title', 'type' => 'text']]]]);

        $this->assertSame('create_model', $action['type']);
        $this->assertSame('Books', $action['params']['name']);
        $this->assertCount(1, $action['params']['fields']);
    }

    public function testTheEarlierListOfIdAndValueIsRead(): void
    {
        $action = $this->actions->normalise(['type' => 'create_model', 'params' => [['id' => 'name', 'value' => 'Books'], ['id' => 'field', 'value' => [['id' => 'title']]]]]);

        $this->assertSame('Books', $action['params']['name']);
        $this->assertSame([['id' => 'title']], $action['params']['fields']);
    }

    public function testAnEntryTakesAMapOfValuesOrTheEarlierList(): void
    {
        $map  = $this->actions->normalise(['type' => 'create_entry', 'params' => ['model_id' => '3', 'values' => ['title' => 'A']]]);
        $list = $this->actions->normalise(['type' => 'create_entry', 'params' => [['id' => 'model_id', 'value' => 3], ['id' => 'fields', 'value' => [['id' => 'title', 'value' => 'A']]]]]);

        $this->assertSame(['model_id' => 3, 'values' => ['title' => 'A']], $map['params']);
        $this->assertSame($map['params'], $list['params']);
    }

    public function testATypeWithAHyphenAndCapitalsIsRead(): void
    {
        $this->assertSame('show_entry', $this->actions->normalise(['type' => 'Show-Entry', 'params' => ['id' => '12']])['type']);
    }

    /**
     * @dataProvider unreadable
     */
    public function testWhatIsNotAnActionIsRefused(mixed $raw): void
    {
        $this->assertNull($this->actions->normalise($raw));
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function unreadable(): iterable
    {
        yield 'not an array'                  => ['create_model'];
        yield 'no type'                       => [['params' => []]];
        yield 'an unknown type'               => [['type' => 'drop_table', 'params' => ['name' => 'x']]];
        yield 'a model without a name'        => [['type' => 'create_model', 'params' => ['fields' => [['id' => 'a']]]]];
        yield 'a model whose fields are a map' => [['type' => 'create_model', 'params' => ['name' => 'X', 'fields' => ['id' => 'a']]]];
        yield 'an entry with no model'        => [['type' => 'create_entry', 'params' => ['values' => ['a' => 1]]]];
        yield 'an entry whose values are a list' => [['type' => 'create_entry', 'params' => ['model_id' => 1, 'values' => [1, 2]]]];
        yield 'an entry to show with no id'   => [['type' => 'show_entry', 'params' => []]];
    }
}
