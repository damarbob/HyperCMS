<?php

use App\Data\Entries\FieldFilters;
use CodeIgniter\Test\CIUnitTestCase;
use StarDust\Filter\Ast\AndNode;
use StarDust\Filter\Ast\LeafNode;

/**
 * The filter parameter of the token API, turned into a StarDust filter on indexed fields.
 *
 * @internal
 */
final class FieldFiltersTest extends CIUnitTestCase
{
    private const INDEXED = ['title' => 'string', 'views' => 'numeric', 'stock' => 'int', 'when' => 'datetime'];

    public function testNoConditionIsNoFilter(): void
    {
        $this->assertNull(FieldFilters::parse([], self::INDEXED));
    }

    public function testAPlainValueIsAnEquality(): void
    {
        $leaf = FieldFilters::parse(['views' => '12.5'], self::INDEXED);

        $this->assertInstanceOf(LeafNode::class, $leaf);
        $this->assertSame('eq', $leaf->operator);
        $this->assertSame('views', $leaf->field->fieldName);
        $this->assertSame(12.5, $leaf->value->value);
    }

    public function testSeveralConditionsMustAllHold(): void
    {
        $and = FieldFilters::parse(['views' => ['gte' => '10', 'lt' => '20'], 'title' => 'Apple'], self::INDEXED);

        $this->assertInstanceOf(AndNode::class, $and);
        $this->assertCount(3, $and->args);
        $this->assertSame(['gte', 'lt', 'eq'], array_map(static fn (LeafNode $leaf): string => $leaf->operator, $and->args));
        $this->assertSame(10, $and->args[0]->value->value, 'A whole number stays an integer.');
    }

    public function testValuesAreReadAsTheTypeOfTheirField(): void
    {
        $this->assertSame(7, FieldFilters::parse(['stock' => '7'], self::INDEXED)->value->value);
        $this->assertSame('007', FieldFilters::parse(['title' => '007'], self::INDEXED)->value->value);
        $this->assertSame('app', FieldFilters::parse(['title' => ['prefix' => 'app']], self::INDEXED)->value->value);
        $this->assertSame([1, 2, 3], FieldFilters::parse(['stock' => ['in' => '1,2,3']], self::INDEXED)->value->value);
        $this->assertNull(FieldFilters::parse(['views' => ['is_null' => '1']], self::INDEXED)->value);
    }

    public function testADateWithoutAnOffsetIsUtc(): void
    {
        $this->assertSame('2025-03-20T10:30:00Z', FieldFilters::parse(['when' => ['gte' => '2025-03-20T10:30']], self::INDEXED)->value->value);
        $this->assertSame('2025-03-20T10:30:00Z', FieldFilters::parse(['when' => ['gte' => '2025-03-20 10:30:00']], self::INDEXED)->value->value);
        $this->assertSame('2025-03-20T10:30:00+07:00', FieldFilters::parse(['when' => ['gte' => '2025-03-20T10:30:00+07:00']], self::INDEXED)->value->value, 'An offset is kept for StarDust to apply.');
    }

    public function testAFieldThatIsNotIndexedIsRefusedWithTheIndexedOnes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('body');
        $this->expectExceptionMessage('title, views');

        FieldFilters::parse(['body' => 'x'], ['title' => 'string', 'views' => 'numeric']);
    }

    public function testAnOperatorOutsideTheListIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('between');

        FieldFilters::parse(['views' => ['between' => '1']], self::INDEXED);
    }

    public function testAValueThatDoesNotFitItsFieldIsRefused(): void
    {
        $bad = [
            ['views', 'abc'], ['views', ''], ['stock', '1.5'], ['stock', 'x'], ['when', 'yesterday'], ['when', '20/03/2025'],
            ['views', ['gt' => ['1']]], ['title', ['in' => '']], ['views', ['prefix' => '1']],
        ];

        foreach ($bad as [$field, $condition]) {
            try {
                FieldFilters::parse([$field => $condition], self::INDEXED);
                $this->fail($field . ' ' . json_encode($condition) . ' should be refused.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($field, $e->getMessage());
            }
        }
    }
}
