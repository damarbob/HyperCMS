<?php

use App\Fields\ConversionProblem;
use App\Fields\FieldTypes;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\StubFiles;

/**
 * How each type reads posted input, checks it, orders it, and reads a value stored in the old format.
 *
 * @internal
 */
final class FieldValuesTest extends CIUnitTestCase
{
    private FieldTypes $types;

    protected function setUp(): void
    {
        parent::setUp();
        $this->types = FieldTypes::core();
        Services::injectMock('files', new StubFiles([1 => ['id' => 1, 'name' => 'a.png'], 2 => ['id' => 2, 'name' => 'b.png']]));
    }

    protected function tearDown(): void
    {
        Services::resetSingle('files');
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function legacy(string $type, mixed $old, array $definition = []): mixed
    {
        return $this->types->get($type)->valueFromLegacy($old, ['id' => 'x', 'type' => $type] + $definition);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function refused(string $type, mixed $old, array $definition = []): void
    {
        try {
            $this->legacy($type, $old, $definition);
            $this->fail("The {$type} value " . json_encode($old) . ' should not convert.');
        } catch (ConversionProblem) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function normalised(string $type, mixed $value, array $definition = []): mixed
    {
        return $this->types->get($type)->normalise($value, ['id' => 'x', 'type' => $type] + $definition);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function checked(string $type, mixed $value, array $definition = []): ?string
    {
        $field = ['id' => 'x', 'label' => 'X', 'type' => $type] + $definition;

        return $this->types->get($type)->validate($this->types->get($type)->normalise($value, $field), $field);
    }

    public function testNumbersFromTheOldFormBecomeNumbers(): void
    {
        $this->assertSame(12.5, $this->legacy('number', '12.50'));
        $this->assertSame(10, $this->legacy('number', '10'));
        $this->assertSame(-3, $this->legacy('number', ' -3 '));
        $this->assertSame(0.5, $this->legacy('number', '.5'));
        $this->assertNull($this->legacy('number', ''));
        $this->assertNull($this->legacy('number', null));
        $this->assertSame(7, $this->legacy('number', '7.0', ['integer' => true]));
        $this->refused('number', 'abc');
        $this->refused('number', ['1']);
        $this->refused('number', '12.5', ['integer' => true]);
    }

    public function testBooleansFromTheOldFormAreReadByTheirText(): void
    {
        foreach (['on', 'ON', 'true', '1', 1, true, 'yes'] as $on) {
            $this->assertTrue($this->legacy('boolean', $on), json_encode($on));
        }
        foreach (['off', 'false', '0', 0, false, '', null] as $off) {
            $this->assertFalse($this->legacy('boolean', $off), json_encode($off));
        }
        $this->refused('boolean', 'maybe');
        $this->refused('boolean', 2);
        $this->refused('boolean', ['on']);
    }

    public function testDatesFromTheOldFormBecomeTheStoredForm(): void
    {
        $this->assertSame('2026-01-05 09:30:00', $this->legacy('datetime', '2026-01-05T09:30'));
        $this->assertSame('2026-02-06 14:05:00', $this->legacy('datetime', '2026-02-06T14:05:00'));
        $this->assertSame('2026-02-06 14:05:00', $this->legacy('datetime', '2026-02-06 14:05:00'));
        $this->assertSame('2026-02-06 14:05:00.5', $this->legacy('datetime', '2026-02-06T14:05:00.5'));
        $this->assertNull($this->legacy('datetime', ''));
        $this->assertNull($this->legacy('datetime', '0000-00-00 00:00:00'));
        $this->assertNull($this->legacy('datetime', null));
        $this->refused('datetime', 'yesterday');
        $this->refused('datetime', '2026-02-30T10:00');
        $this->refused('datetime', '2026-02-06T25:00');
    }

    public function testChoicesFromTheOldFormGetTheirShape(): void
    {
        $many = ['multiple' => true];
        $this->assertSame(['news'], $this->legacy('choice', 'news', $many), 'One ticked box was stored as text.');
        $this->assertSame(['news', 'tech'], $this->legacy('choice', ['news', 'tech'], $many));
        $this->assertSame([], $this->legacy('choice', [], $many));
        $this->assertSame([], $this->legacy('choice', null, $many), 'No ticked box left no key.');
        $this->assertSame(['1', '2'], $this->legacy('choice', [1, 2], $many));
        $this->refused('choice', [['a']], $many);

        $this->assertSame('a', $this->legacy('choice', 'a'));
        $this->assertNull($this->legacy('choice', ''));
        $this->assertNull($this->legacy('choice', null));
        $this->assertSame('3', $this->legacy('choice', 3));
        $this->refused('choice', ['a', 'b']);
    }

    public function testRelationsAndModelsBecomeIds(): void
    {
        $this->assertSame(7, $this->legacy('relation', '7', ['multiple' => false]));
        $this->assertSame(7, $this->legacy('model', '7'));
        $this->assertNull($this->legacy('relation', ''));
        $this->assertNull($this->legacy('model', null));
        $this->assertSame([1, 2], $this->legacy('relation', ['1', '2'], ['multiple' => true]));
        $this->assertSame([], $this->legacy('relation', null, ['multiple' => true]));
        $this->refused('relation', 'abc');
        $this->refused('model', '0');
        $this->refused('relation', '1.5');
    }

    public function testFilesFromTheOldFormKeepTheirShapeUntilTheyAreMoved(): void
    {
        $this->assertNull($this->legacy('file', ''));
        $this->assertNull($this->legacy('file', []));
        $this->assertSame('uploads/a-1.png', $this->legacy('file', ['uploads/a-1.png']), 'A single upload was stored as a list.');
        $this->assertSame('http://h/public/file-server/serve/aa', $this->legacy('file', 'http://h/public/file-server/serve/aa'));
        $this->assertSame(['a', 'b'], $this->legacy('file', ['a', 'b'], ['multiple' => true]));
        $this->assertSame(['a'], $this->legacy('file', 'a', ['multiple' => true]));
        $this->assertSame([], $this->legacy('file', '', ['multiple' => true]));
        $this->assertSame(12, $this->legacy('file', 12), 'A value already converted is kept.');
        $this->refused('file', [['x']]);
        $this->refused('file', true);
    }

    public function testColorsHooksHiddenAndTextsFromTheOldForm(): void
    {
        $this->assertSame('#aabbcc', $this->legacy('color', '#AABBCC'));
        $this->assertNull($this->legacy('color', ''));
        $this->refused('color', 'red');

        $this->assertSame('page.render', $this->legacy('hook', 'page.render'));
        $this->assertNull($this->legacy('hook', ''));

        $this->assertSame('secret', $this->legacy('hidden', 'secret'));
        $this->assertSame('5', $this->legacy('hidden', 5));

        $this->assertSame('  Leading space title', $this->legacy('text', '  Leading space title'), 'Text is kept as it is.');
        $this->assertSame('', $this->legacy('longtext', ''));
        $this->assertSame('<p>x</p>', $this->legacy('richtext', '<p>x</p>'));
        $this->refused('text', ['a']);
        $this->refused('code', ['a']);
    }

    public function testPostedInputIsNormalisedToTheStoredShape(): void
    {
        $this->assertSame(12.5, $this->normalised('number', '12.50'));
        $this->assertNull($this->normalised('number', ' '));
        $this->assertSame(3, $this->normalised('number', '3.0', ['integer' => true]));
        $this->assertSame('abc', $this->normalised('number', 'abc'), 'Unreadable input is returned as it came.');

        $this->assertTrue($this->normalised('boolean', 'on'));
        $this->assertFalse($this->normalised('boolean', null));

        $this->assertSame('2026-01-05 09:30:00', $this->normalised('datetime', '2026-01-05T09:30'));
        $this->assertNull($this->normalised('datetime', ''));

        $this->assertSame([], $this->normalised('choice', null, ['multiple' => true]));
        $this->assertSame(['a'], $this->normalised('choice', 'a', ['multiple' => true]));
        $this->assertNull($this->normalised('choice', ''));

        $this->assertSame(7, $this->normalised('relation', '7'));
        $this->assertSame([1, 2], $this->normalised('relation', ['1', 2], ['multiple' => true]));
        $this->assertSame(['1', 'x'], $this->normalised('relation', ['1', 'x'], ['multiple' => true]));
        $this->assertSame('#aabbcc', $this->normalised('color', ' #AABBCC '));
    }

    public function testValidationReportsWhatIsWrongAndPassesWhatIsRight(): void
    {
        $this->assertNull($this->checked('text', 'hello'));
        $this->assertNull($this->checked('text', ''));
        $this->assertStringContainsString('X', (string) $this->checked('text', '', ['required' => true]));
        $this->assertNotNull($this->checked('text', 'nope', ['format' => 'email']));
        $this->assertNull($this->checked('text', 'a@b.co', ['format' => 'email']));
        $this->assertNotNull($this->checked('text', 'nope', ['format' => 'url']));
        $this->assertNotNull($this->checked('text', str_repeat('a', 4097), ['filterable' => true]));
        $this->assertNull($this->checked('text', str_repeat('a', 4097)));

        $this->assertNull($this->checked('number', '5', ['min' => 1, 'max' => 9]));
        $this->assertNotNull($this->checked('number', '0', ['min' => 1]));
        $this->assertNotNull($this->checked('number', '10', ['max' => 9]));
        $this->assertNotNull($this->checked('number', 'abc'));
        $this->assertNotNull($this->checked('number', '1.5', ['integer' => true]));
        $this->assertNull($this->checked('number', '', []));

        $this->assertNull($this->checked('boolean', 'off'));
        $this->assertNotNull($this->checked('boolean', 'off', ['required' => true]));
        $this->assertNull($this->checked('boolean', 'on', ['required' => true]));
        $this->assertNotNull($this->checked('boolean', 'maybe'));

        $this->assertNull($this->checked('datetime', '2026-01-05T09:30'));
        $this->assertNotNull($this->checked('datetime', 'soon'));

        $choices = ['choices' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]];
        $this->assertNull($this->checked('choice', 'a', $choices));
        $this->assertNotNull($this->checked('choice', 'z', $choices));
        $this->assertNull($this->checked('choice', ['a', 'b'], $choices + ['multiple' => true]));
        $this->assertNotNull($this->checked('choice', ['a', 'z'], $choices + ['multiple' => true]));
        $this->assertNotNull($this->checked('choice', [], $choices + ['multiple' => true, 'required' => true]));

        $this->assertNull($this->checked('color', '#AABBCC'));
        $this->assertNotNull($this->checked('color', 'blue'));

        $this->assertNull($this->checked('relation', '3'));
        $this->assertNotNull($this->checked('relation', 'abc'));
        $this->assertNotNull($this->checked('relation', '0'));
        $this->assertNull($this->checked('file', ['1', '2'], ['multiple' => true]));
        $this->assertNotNull($this->checked('file', ['1', '9'], ['multiple' => true]), 'A file that is not in the library is refused.');
        $this->assertNotNull($this->checked('file', 9));
    }

    public function testEachTypeNamesTheStarDustKindItIsIndexedAs(): void
    {
        $kind = fn (string $type, array $definition = []): ?string => $this->types->get($type)->kind(['type' => $type] + $definition);

        $this->assertSame('string', $kind('text'));
        $this->assertSame('string', $kind('color'));
        $this->assertSame('string', $kind('hook'));
        $this->assertSame('string', $kind('choice', ['multiple' => false]));
        $this->assertNull($kind('choice', ['multiple' => true]));
        $this->assertSame('numeric', $kind('number', ['integer' => false]));
        $this->assertSame('int', $kind('number', ['integer' => true]));
        $this->assertSame('datetime', $kind('datetime'));
        $this->assertSame('int', $kind('relation'));
        $this->assertNull($kind('relation', ['multiple' => true]));
        $this->assertSame('int', $kind('model'));
        foreach (['longtext', 'richtext', 'code', 'boolean', 'file', 'hidden'] as $type) {
            $this->assertNull($kind($type), $type);
        }
    }

    public function testSortKeysOrderLikeTheListsDoToday(): void
    {
        $key = fn (string $type, mixed $value): string|float|null => $this->types->get($type)->sortKey($value);

        $this->assertSame('ECLAIR', $key('text', 'Éclair'));
        $this->assertSame(12.5, $key('number', 12.5));
        $this->assertSame(3.0, $key('number', 3));
        $this->assertNull($key('number', null));
        $this->assertSame('2026-01-05 09:30:00', $key('datetime', '2026-01-05 09:30:00'));
        $this->assertNull($key('datetime', 'soon'));
        $this->assertSame(1.0, $key('boolean', true));
        $this->assertSame(0.0, $key('boolean', false));
        $this->assertSame(7.0, $key('relation', 7));
        $this->assertNull($key('relation', null));
        $this->assertSame('NEWS', $key('choice', 'News'));
        $this->assertNull($key('choice', ['a']));
    }
}
