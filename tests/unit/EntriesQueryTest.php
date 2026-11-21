<?php

use App\Libraries\EntriesQuery;
use App\Libraries\UnsupportedDataQuery;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The PHP subset that answers a data query on `table: entries`, run on three small entries, and the refusal of
 * everything outside it.
 *
 * @internal
 */
final class EntriesQueryTest extends CIUnitTestCase
{
    /** @var array<int, array> */
    private array $fields = [
        1 => ['title' => 'Éclair', 'price' => '9', 'tags' => ['a', 'b']],
        2 => ['title' => 'apple', 'price' => '10'],
        3 => ['title' => 'Linked page'],
    ];

    private function query(): EntriesQuery
    {
        $flat = [
            ['id' => '1', 'model_id' => '1', 'model_name' => 'Article', 'sd_entry_id' => '11', 'payload_bytes' => '10'],
            ['id' => '2', 'model_id' => '1', 'model_name' => 'Article', 'sd_entry_id' => '12', 'payload_bytes' => '10'],
            ['id' => '3', 'model_id' => '2', 'model_name' => 'Page', 'sd_entry_id' => '13', 'payload_bytes' => '10'],
        ];

        return new EntriesQuery(
            static fn (): array => $flat,
            function (array $rows): Generator {
                // One entry per chunk, to show that the answer does not depend on the chunking.
                foreach ($rows as $row) {
                    yield [(int) $row['id'] => $this->fields[(int) $row['id']]];
                }
            },
        );
    }

    public function testSelectsIdAndFieldsUnderAliases(): void
    {
        $rows = $this->query()->run(['table' => 'entries', 'select' => 'id as value, fields as label']);

        $this->assertCount(3, $rows);
        $this->assertSame(['value' => '2', 'label' => '{"title":"apple","price":"10"}'], $rows[1]);
    }

    public function testSelectsAFieldPlaceholderAndAModelColumn(): void
    {
        $rows = $this->query()->run(['select' => 'id as value, {{field:title}} as label, model_name', 'where' => [['column' => 'model_id', 'operator' => '=', 'value' => 1]]]);

        $this->assertSame([
            ['value' => '1', 'label' => 'Éclair', 'model_name' => 'Article'],
            ['value' => '2', 'label' => 'apple', 'model_name' => 'Article'],
        ], $rows);
    }

    public function testAFieldThatIsAListIsGivenAsJsonText(): void
    {
        $rows = $this->query()->run(['select' => '{{field:tags}} as tags, {{field:price}} as price', 'where' => ['model_id' => 1]]);

        $this->assertSame('["a", "b"]', $rows[0]['tags']);
        $this->assertNull($rows[1]['tags']);
    }

    public function testWhereOnModelNameIgnoresCaseAndAcceptsLists(): void
    {
        $query = $this->query();

        $this->assertCount(1, $query->run(['select' => 'id', 'where' => [['column' => 'model_name', 'operator' => '=', 'value' => 'page']]]));
        $this->assertCount(3, $query->run(['select' => 'id', 'where' => [['column' => 'model_name', 'operator' => 'IN', 'value' => ['Article', 'Page']]]]));
        $this->assertCount(2, $query->run(['select' => 'id', 'where' => [['column' => 'model_id', 'operator' => '!=', 'value' => '2']]]));
        $this->assertCount(2, $query->run(['select' => 'id', 'where' => ["model_name = 'Article'"]]));
        $this->assertCount(2, $query->run(['select' => 'id', 'where' => [['column' => 'model_name', 'operator' => 'LIKE', 'value' => 'art%']]]));
    }

    public function testLikeOnFieldsIgnoresCaseAndAccents(): void
    {
        $rows = $this->query()->run(['select' => 'id', 'like' => ['fields' => 'ECLAIR']]);

        $this->assertSame([['id' => '1']], $rows);
    }

    public function testOrdersByAFieldAndByANumber(): void
    {
        $query = $this->query();

        $this->assertSame(['2', '1', '3'], array_column($query->run(['select' => 'id', 'orderby' => '{{field:title}} ASC']), 'id'));
        $this->assertSame(['2', '1', '3'], array_column($query->run(['select' => 'id', 'orderby' => '{{field:price}} DESC']), 'id'));
        $this->assertSame(['3', '2', '1'], array_column($query->run(['select' => 'id as value', 'orderby' => 'value DESC']), 'value'));
    }

    public function testLimitOffsetAndCount(): void
    {
        $query = $this->query();

        $this->assertSame([['id' => '2']], $query->run(['select' => 'id', 'limit' => 1, 'offset' => 1]));
        $this->assertSame(3, $query->run(['select' => 'id', 'count' => true]));
        $this->assertSame(1, $query->run(['select' => 'id', 'like' => ['fields' => 'linked'], 'count' => true]));
    }

    /**
     * @return array<string, array{0: array, 1: string}>
     */
    public static function refusedQueries(): array
    {
        return [
            'joins'               => [['select' => 'id', 'joins' => [['table' => 'users', 'condition' => 'a = b', 'type' => 'left']]], 'joins'],
            'group by'            => [['select' => 'id', 'groupby' => 'model_id'], 'groupby'],
            'having'              => [['select' => 'id', 'having' => [['column' => 'id', 'operator' => '>', 'value' => 1]]], 'having'],
            'another column'      => [['select' => 'id, created_by'], 'select created_by'],
            'star'                => [['select' => '*'], 'select *'],
            'no select'           => [[], 'select without columns'],
            'a model placeholder' => [['select' => '{{model_field:title.type}}'], 'select {{model_field:title.type}}'],
            'where on a column'   => [['select' => 'id', 'where' => [['column' => 'created_by', 'operator' => '=', 'value' => 'x']]], 'where on column created_by'],
            'where on fields'     => [['select' => 'id', 'where' => ['fields' => 'x']], 'where on column fields'],
            'an operator'         => [['select' => 'id', 'where' => [['column' => 'model_id', 'operator' => 'BETWEEN', 'value' => 1]]], 'operator BETWEEN'],
            'raw where text'      => [['select' => 'id', 'where' => ['id = 1 OR 1 = 1']], 'where id = 1 OR 1 = 1'],
            'like on a column'    => [['select' => 'id', 'like' => ['model_name' => 'a']], 'like on model_name'],
            'order by a column'   => [['select' => 'id', 'orderby' => 'created_by'], 'orderby created_by'],
            'an unknown key'      => [['select' => 'id', 'distinct' => true], 'key distinct'],
        ];
    }

    #[DataProvider('refusedQueries')]
    public function testRefusesAQueryOutsideTheSubset(array $params, string $part): void
    {
        try {
            $this->query()->run($params);
            $this->fail('The query should have been refused.');
        } catch (UnsupportedDataQuery $e) {
            $this->assertSame($part, $e->part);
        }
    }

    public function testTheRefusalNamesWhatWasRefusedInBothLanguages(): void
    {
        foreach (['en', 'id'] as $locale) {
            $message = lang('SyntaxProcessor.unsupportedEntriesQuery', ['part' => 'joins'], $locale);

            $this->assertStringContainsString('(joins)', $message);
            $this->assertStringNotContainsString('SyntaxProcessor.', $message);
        }
    }
}
