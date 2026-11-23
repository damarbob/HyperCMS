<?php

use App\Fields\DefinitionCheck;
use App\Fields\FieldCatalog;
use App\Fields\FieldTypes;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * A model definition is checked against version 2 before it is saved, and comes back in the shape it is stored in.
 *
 * @internal
 */
final class DefinitionCheckTest extends CIUnitTestCase
{
    private DefinitionCheck $check;

    protected function setUp(): void
    {
        parent::setUp();
        $this->check = new DefinitionCheck(FieldTypes::core(), [1 => 'Article', 2 => 'Product']);
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return list<string> The messages for the field.
     */
    private function problems(array $field): array
    {
        $result = $this->check->check([$field]);

        return array_column($result['errors'], 'message');
    }

    public function testTheArticleDefinitionOfTheConverterPassesUnchanged(): void
    {
        $article = [
            ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['id' => 'body', 'label' => 'Body', 'type' => 'longtext'],
            ['id' => 'views', 'label' => 'Views', 'type' => 'number', 'integer' => false, 'widget' => 'input'],
            ['id' => 'published_at', 'label' => 'Published', 'type' => 'datetime'],
            ['id' => 'tags', 'label' => 'Tags', 'type' => 'choice', 'multiple' => true, 'widget' => 'checkboxes', 'choices' => [['value' => 'news', 'label' => 'News'], ['value' => 'tech', 'label' => 'Tech']]],
            ['id' => 'cover', 'label' => 'Cover', 'type' => 'file', 'multiple' => false, 'accept' => 'any'],
        ];

        $result = $this->check->check($article);

        $this->assertSame([], $result['errors']);
        $this->assertSame($article, $result['fields']);
    }

    public function testTheDefinitionMustBeAListOfObjects(): void
    {
        $this->assertNotSame([], $this->check->check(['id' => 'x'])['errors']);
        $this->assertNotSame([], $this->check->check('text')['errors']);
        $this->assertNotSame([], $this->check->check(['text'])['errors']);
    }

    public function testAnIdMustBeSafeAndUnique(): void
    {
        $this->assertNotSame([], $this->problems(['id' => '1st', 'type' => 'text']));
        $this->assertNotSame([], $this->problems(['id' => 'a b', 'type' => 'text']));
        $this->assertNotSame([], $this->problems(['id' => '', 'type' => 'text']));

        $twice = $this->check->check([['id' => 'a', 'type' => 'text'], ['id' => 'a', 'type' => 'text']]);
        $this->assertCount(1, $twice['errors']);
        $this->assertSame('a', $twice['errors'][0]['field']);
    }

    public function testAnUnknownTypeIsReported(): void
    {
        $this->assertNotSame([], $this->problems(['id' => 'a', 'type' => 'textarea']));
        $this->assertNotSame([], $this->problems(['id' => 'a']));
    }

    public function testALabelDefaultsToTheId(): void
    {
        $result = $this->check->check([['id' => 'title', 'type' => 'text']]);

        $this->assertSame('title', $result['fields'][0]['label']);
    }

    public function testASelectOptionMustBeOneOfItsChoices(): void
    {
        $this->assertNotSame([], $this->problems(['id' => 'a', 'type' => 'text', 'format' => 'phone']));
        $this->assertSame([], $this->problems(['id' => 'a', 'type' => 'text', 'format' => 'email']));
    }

    public function testNumbersAreCastAndRangesAreChecked(): void
    {
        $result = $this->check->check([['id' => 'n', 'type' => 'number', 'min' => '1', 'max' => '10', 'integer' => 1]]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['fields'][0]['min']);
        $this->assertSame(10, $result['fields'][0]['max']);
        $this->assertTrue($result['fields'][0]['integer']);

        $this->assertNotSame([], $this->problems(['id' => 'n', 'type' => 'number', 'min' => 5, 'max' => 2]));
        $this->assertNotSame([], $this->problems(['id' => 'n', 'type' => 'number', 'min' => 'low']));
    }

    public function testAChoiceNeedsUniqueChoicesWithValues(): void
    {
        $this->assertNotSame([], $this->problems(['id' => 'c', 'type' => 'choice']));
        $this->assertNotSame([], $this->problems(['id' => 'c', 'type' => 'choice', 'choices' => []]));
        $this->assertNotSame([], $this->problems(['id' => 'c', 'type' => 'choice', 'choices' => [['value' => 'a'], ['value' => 'a']]]));
        $this->assertNotSame([], $this->problems(['id' => 'c', 'type' => 'choice', 'choices' => [['value' => '', 'label' => 'x']]]));

        $result = $this->check->check([['id' => 'c', 'type' => 'choice', 'choices' => [['value' => 'a']]]]);
        $this->assertSame([], $result['errors']);
        $this->assertSame([['value' => 'a', 'label' => 'a']], $result['fields'][0]['choices']);
    }

    public function testARelationNeedsAnExistingModel(): void
    {
        $this->assertNotSame([], $this->problems(['id' => 'r', 'type' => 'relation']));
        $this->assertNotSame([], $this->problems(['id' => 'r', 'type' => 'relation', 'model' => 99]));
        $this->assertSame([], $this->problems(['id' => 'r', 'type' => 'relation', 'model' => '2']));
    }

    public function testOnlyATypeThatCanBeIndexedMayBeFilterable(): void
    {
        $this->assertSame([], $this->problems(['id' => 'a', 'type' => 'text', 'filterable' => true]));
        $this->assertNotSame([], $this->problems(['id' => 'a', 'type' => 'longtext', 'filterable' => true]));
        $this->assertNotSame([], $this->problems(['id' => 'a', 'type' => 'choice', 'multiple' => true, 'choices' => [['value' => 'a']], 'filterable' => true]));
    }

    public function testADefaultMustFitTheField(): void
    {
        $this->assertNotSame([], $this->problems(['id' => 'n', 'type' => 'number', 'default' => 'many']));
        $this->assertNotSame([], $this->problems(['id' => 'c', 'type' => 'choice', 'choices' => [['value' => 'a']], 'default' => 'b']));
        $this->assertSame([], $this->problems(['id' => 'n', 'type' => 'number', 'default' => '3']));
    }

    public function testOffFlagsAndEmptyHelpAreLeftOut(): void
    {
        $result = $this->check->check([['id' => 'a', 'type' => 'text', 'required' => false, 'filterable' => false, 'help' => '']]);

        $this->assertSame(['id' => 'a', 'label' => 'a', 'type' => 'text'], $result['fields'][0]);
    }

    public function testAKeyThatTheCheckDoesNotKnowIsKept(): void
    {
        $result = $this->check->check([['id' => 'a', 'type' => 'text', 'x-module' => ['k' => 1]]]);

        $this->assertSame(['k' => 1], $result['fields'][0]['x-module']);
    }

    public function testTheLabelFieldMustBeAFieldThatHoldsOnePlainValue(): void
    {
        $fields = [
            ['id' => 'title', 'type' => 'text'],
            ['id' => 'secret', 'type' => 'text', 'format' => 'password'],
            ['id' => 'tags', 'type' => 'choice', 'multiple' => true],
            ['id' => 'cover', 'type' => 'file'],
        ];

        $this->assertNull($this->check->checkLabelField(null, $fields));
        $this->assertNull($this->check->checkLabelField('', $fields));
        $this->assertNull($this->check->checkLabelField('title', $fields));
        $this->assertNotNull($this->check->checkLabelField('secret', $fields));
        $this->assertNotNull($this->check->checkLabelField('tags', $fields));
        $this->assertNotNull($this->check->checkLabelField('cover', $fields));
        $this->assertNotNull($this->check->checkLabelField('gone', $fields));
    }

    public function testTheCatalogDescribesEveryTypeForTheBuilder(): void
    {
        $catalog = (new FieldCatalog(FieldTypes::core()))->forBuilder([['id' => 1, 'name' => 'Article']]);
        $byName  = array_column($catalog['types'], null, 'name');

        $this->assertSame(FieldTypes::core()->names(), array_keys($byName));
        $this->assertSame('always', $byName['text']['filterable']);
        $this->assertSame('single', $byName['choice']['filterable']);
        $this->assertSame('never', $byName['longtext']['filterable']);
        $this->assertFalse($byName['code']['listDefault']);
        $this->assertTrue($byName['number']['listDefault']);

        $format = array_column($byName['text']['settings'], null, 'key')['format'];
        $this->assertSame([['value' => 'plain', 'label' => lang('Fields.formatPlain')]], array_slice($format['choices'], 0, 1));
        $this->assertSame([['id' => 1, 'name' => 'Article']], $catalog['models']);
    }
}
