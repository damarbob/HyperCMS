<?php

use App\Data\Entries\FilterableFields;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Which field types can be filterable, the StarDust type each one is indexed as, and the clean-up of a value
 * on its way in.
 *
 * @internal
 */
final class FilterableFieldsTest extends CIUnitTestCase
{
    public function testTypesMapToStarDustTypes(): void
    {
        foreach (['text', 'color', 'hook'] as $type) {
            $this->assertSame('string', FilterableFields::kind(['id' => 'x', 'type' => $type]), $type);
        }
        $this->assertSame('string', FilterableFields::kind(['id' => 'x', 'type' => 'choice', 'multiple' => false]));
        $this->assertSame('numeric', FilterableFields::kind(['id' => 'x', 'type' => 'number']));
        $this->assertSame('datetime', FilterableFields::kind(['id' => 'x', 'type' => 'datetime']));
        $this->assertSame('int', FilterableFields::kind(['id' => 'x', 'type' => 'model']));
        $this->assertSame('int', FilterableFields::kind(['id' => 'x', 'type' => 'relation', 'model' => 1]));
    }

    public function testNumberIsAnIntegerOnlyWhenItSaysSo(): void
    {
        $this->assertSame('int', FilterableFields::kind(['type' => 'number', 'integer' => true]));
        $this->assertSame('numeric', FilterableFields::kind(['type' => 'number', 'integer' => false, 'step' => 0.01]));
        $this->assertSame('numeric', FilterableFields::kind(['type' => 'number']));
    }

    public function testTypesThatCannotBeFilterableHaveNoKind(): void
    {
        foreach (['longtext', 'richtext', 'code', 'boolean', 'file', 'hidden', 'nonsense'] as $type) {
            $this->assertNull(FilterableFields::kind(['id' => 'x', 'type' => $type]), $type);
        }
        $this->assertNull(FilterableFields::kind(['id' => 'x', 'type' => 'choice', 'multiple' => true]));
        $this->assertNull(FilterableFields::kind(['id' => 'x', 'type' => 'relation', 'multiple' => true]));
        $this->assertNull(FilterableFields::kind(['id' => 'x']));
    }

    public function testTheTypesThatCanBeFilterableAreListedForTheMessage(): void
    {
        $this->assertSame(['text', 'number', 'choice', 'datetime', 'color', 'relation', 'model', 'hook'], FilterableFields::types());
    }

    public function testTheFlagIsReadFromTheDefinitionAndIsOffByDefault(): void
    {
        $definition = json_encode([
            ['id' => 'title', 'type' => 'text', 'filterable' => true],
            ['id' => 'views', 'type' => 'number', 'filterable' => 'true', 'integer' => true],
            ['id' => 'when', 'type' => 'datetime', 'filterable' => true],
            ['id' => 'plain', 'type' => 'text'],
            ['id' => 'off', 'type' => 'text', 'filterable' => false],
            ['id' => 'body', 'type' => 'longtext', 'filterable' => true],
            ['id' => 'title', 'type' => 'number', 'filterable' => true],
        ]);

        $read = FilterableFields::read($definition);

        $this->assertSame(['title' => 'string', 'views' => 'int', 'when' => 'datetime'], $read['fields'], 'The first entry of a repeated id counts.');
        $this->assertSame(['body' => 'longtext'], $read['refused']);
        $this->assertSame(['fields' => [], 'refused' => []], FilterableFields::read(null));
        $this->assertSame(['fields' => [], 'refused' => []], FilterableFields::read('not json'));
    }

    public function testADefinitionWithTheFlagOnATypeThatCannotTakeItIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('body');

        FilterableFields::assertValid('[{"id":"body","type":"longtext","filterable":true}]');
    }

    public function testADefinitionThatIsAllowedPasses(): void
    {
        FilterableFields::assertValid('[{"id":"title","type":"text","filterable":true},{"id":"body","type":"longtext"}]');
        FilterableFields::assertValid(null);

        $this->addToAssertionCount(1);
    }

    public function testADateGetsASpaceAndSeconds(): void
    {
        $this->assertSame('2025-03-20 10:30:00', FilterableFields::value('d', 'datetime', '2025-03-20T10:30'));
        $this->assertSame('2025-03-20 10:30:45', FilterableFields::value('d', 'datetime', '2025-03-20T10:30:45'));
        $this->assertSame('2025-03-20 10:30:00', FilterableFields::value('d', 'datetime', '2025-03-20 10:30:00'));
        $this->assertSame('2025-03-20 10:30:00.250', FilterableFields::value('d', 'datetime', '2025-03-20T10:30:00.250'));
        $this->assertSame('2025-03-20 10:30:00', FilterableFields::value('d', 'datetime', ' 2025-03-20T10:30 '));
    }

    public function testABlankOrZeroDateIsNullAndNeverZero(): void
    {
        foreach (['', '   ', '0000-00-00T00:00', '0000-00-00 00:00:00', null] as $blank) {
            $this->assertNull(FilterableFields::value('d', 'datetime', $blank), var_export($blank, true));
        }
    }

    public function testADateThatDoesNotExistIsRefused(): void
    {
        foreach (['2025-02-31T10:00', '2025-13-01T10:00', '2025-03-20T24:00', '20/03/2025', 'tomorrow', '2025-03-20'] as $bad) {
            try {
                FilterableFields::value('when', 'datetime', $bad);
                $this->fail("$bad should be refused.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('when', $e->getMessage());
            }
        }
    }

    public function testABlankNumberIsNullAndNeverZero(): void
    {
        $this->assertNull(FilterableFields::value('n', 'numeric', ''));
        $this->assertNull(FilterableFields::value('n', 'numeric', '  '));
        $this->assertNull(FilterableFields::value('n', 'numeric', null));
        $this->assertNull(FilterableFields::value('n', 'int', ''));
    }

    public function testANumberKeepsTheWayItWasWritten(): void
    {
        $this->assertSame('12.50', FilterableFields::value('n', 'numeric', '12.50'));
        $this->assertSame('-3', FilterableFields::value('n', 'numeric', ' -3 '));
        $this->assertSame('1e3', FilterableFields::value('n', 'numeric', '1e3'));
        $this->assertSame(12, FilterableFields::value('n', 'numeric', 12));
        $this->assertSame(1.5, FilterableFields::value('n', 'numeric', 1.5));
        $this->assertSame('0', FilterableFields::value('n', 'numeric', '0'));
    }

    public function testANumberStarDustsBackfillWouldNotReadIsWrittenOut(): void
    {
        $this->assertSame('0.5', FilterableFields::value('n', 'numeric', '.5'));
        $this->assertSame('5', FilterableFields::value('n', 'numeric', '+5'));
        $this->assertSame('7', FilterableFields::value('n', 'numeric', '007'));
    }

    public function testTextThatIsNotANumberIsRefused(): void
    {
        foreach (['abc', '12abc', 'NaN', '1,5', true, [1]] as $bad) {
            try {
                FilterableFields::value('price', 'numeric', $bad);
                $this->fail(var_export($bad, true) . ' should be refused.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('price', $e->getMessage());
            }
        }
    }

    public function testAnIntegerTakesWholeNumbersOnly(): void
    {
        $this->assertSame(12, FilterableFields::value('n', 'int', '12'));
        $this->assertSame(-7, FilterableFields::value('n', 'int', ' -7 '));
        $this->assertSame(7, FilterableFields::value('n', 'int', '007'));
        $this->assertSame(0, FilterableFields::value('n', 'int', '-0'));
        $this->assertSame(5, FilterableFields::value('n', 'int', 5.0));

        foreach (['1.5', '12abc', '99999999999999999999', 1.5] as $bad) {
            try {
                FilterableFields::value('n', 'int', $bad);
                $this->fail(var_export($bad, true) . ' should be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testATextIsKeptAsItIsAndALongOneOrAListIsRefused(): void
    {
        $this->assertSame('', FilterableFields::value('t', 'string', ''));
        $this->assertSame('on', FilterableFields::value('t', 'string', 'on'));
        $this->assertSame('  spaced ', FilterableFields::value('t', 'string', '  spaced '));
        $this->assertSame(str_repeat('a', 4096), FilterableFields::value('t', 'string', str_repeat('a', 4096)));

        foreach ([str_repeat('a', 4097), ['a', 'b']] as $bad) {
            try {
                FilterableFields::value('t', 'string', $bad);
                $this->fail('The value should be refused.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('t', $e->getMessage());
            }
        }
    }

    public function testOnlyTheFilterableFieldsAreTouched(): void
    {
        $fields = [
            'when'  => '2025-03-20T10:30',
            'views' => '',
            'free'  => '2025-03-20T10:30',
            'blank' => '',
            'tags'  => ['a', 'b'],
        ];

        $clean = FilterableFields::normalise($fields, ['when' => 'datetime', 'views' => 'numeric', 'missing' => 'string']);

        $this->assertSame(
            ['when' => '2025-03-20 10:30:00', 'views' => null, 'free' => '2025-03-20T10:30', 'blank' => '', 'tags' => ['a', 'b']],
            $clean,
        );
        $this->assertSame($fields, FilterableFields::normalise($fields, []), 'Without a filterable field nothing changes.');
    }
}
