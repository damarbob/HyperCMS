<?php

use App\Fields\FieldTypes;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * An old field definition becomes a version 2 definition of the right type.
 *
 * @internal
 */
final class FieldDefinitionsTest extends CIUnitTestCase
{
    private FieldTypes $types;

    protected function setUp(): void
    {
        parent::setUp();
        $this->types = FieldTypes::core();
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function convert(array $old, array $context = []): array
    {
        $result = $this->types->fromLegacy($old, $context);
        $this->assertNull($result['problem'], (string) $result['problem']);

        return $result['field'];
    }

    public function testTheArticleModelOfTheSeedBecomesTheDefinitionInThePlan(): void
    {
        $old = [
            ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['id' => 'body', 'label' => 'Body', 'type' => 'textarea'],
            ['id' => 'views', 'label' => 'Views', 'type' => 'number'],
            ['id' => 'published_at', 'label' => 'Published', 'type' => 'datetime-local'],
            ['id' => 'tags', 'label' => 'Tags', 'type' => 'checkboxes', 'options' => [['value' => 'news', 'label' => 'News'], ['value' => 'tech', 'label' => 'Tech']]],
            ['id' => 'cover', 'label' => 'Cover', 'type' => 'file'],
        ];

        $this->assertSame([
            ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['id' => 'body', 'label' => 'Body', 'type' => 'longtext'],
            ['id' => 'views', 'label' => 'Views', 'type' => 'number', 'integer' => false, 'widget' => 'input'],
            ['id' => 'published_at', 'label' => 'Published', 'type' => 'datetime'],
            ['id' => 'tags', 'label' => 'Tags', 'type' => 'choice', 'multiple' => true, 'widget' => 'checkboxes', 'choices' => [['value' => 'news', 'label' => 'News'], ['value' => 'tech', 'label' => 'Tech']]],
            ['id' => 'cover', 'label' => 'Cover', 'type' => 'file', 'multiple' => false, 'accept' => 'any'],
        ], array_map(fn (array $field): array => $this->convert($field), $old));
    }

    public function testTextFormatsAndTheSharedKeys(): void
    {
        $this->assertSame(
            ['id' => 'mail', 'label' => 'Mail', 'type' => 'text', 'format' => 'email', 'required' => true, 'help' => 'Visit [base_url]', 'default' => 'a@b.c', 'filterable' => true],
            $this->convert(['id' => 'mail', 'label' => 'Mail', 'type' => 'email', 'required' => 'true', 'helper' => 'Visit [base_url]', 'value' => 'a@b.c', 'filterable' => true, 'className' => 'x']),
        );
        $this->assertSame('password', $this->convert(['id' => 'p', 'type' => 'password'])['format']);
        $this->assertSame('url', $this->convert(['id' => 'p', 'type' => 'url'])['format']);
        $this->assertSame('p', $this->convert(['id' => 'p', 'type' => 'text'])['label'], 'A missing label is the id.');
    }

    public function testATextareaIsDecidedByItsClassesAndKeepsTheLanguage(): void
    {
        $this->assertSame('richtext', $this->convert(['id' => 'a', 'type' => 'textarea', 'className' => 'hyper-rich-text-field'])['type']);
        $this->assertSame(
            ['id' => 'a', 'label' => 'a', 'type' => 'code', 'language' => 'html'],
            $this->convert(['id' => 'a', 'type' => 'textarea', 'className' => 'foo hyper-code-field', 'data' => ['language' => 'html']]),
        );
        $this->assertSame('plaintext', $this->convert(['id' => 'a', 'type' => 'textarea', 'className' => 'hyper-code-field'])['language']);
        $this->assertSame('longtext', $this->convert(['id' => 'a', 'type' => 'textarea', 'className' => 'something-else'])['type']);
    }

    public function testAUrlWithTheFileBrowseClassIsASingleFile(): void
    {
        $this->assertSame(
            ['id' => 'f', 'label' => 'f', 'type' => 'file', 'multiple' => false, 'accept' => 'any'],
            $this->convert(['id' => 'f', 'type' => 'url', 'className' => 'hyper-file-browse-field']),
        );
        $this->assertSame('text', $this->convert(['id' => 'f', 'type' => 'text', 'className' => 'hyper-file-browse-field'])['type']);
    }

    public function testNumbersKeepTheirLimitsAndAStepOfOneMakesThemWhole(): void
    {
        $this->assertSame(
            ['id' => 'n', 'label' => 'n', 'type' => 'number', 'integer' => true, 'min' => 1, 'max' => 9, 'widget' => 'input'],
            $this->convert(['id' => 'n', 'type' => 'number', 'options' => ['step' => '1', 'min' => '1', 'max' => 9]]),
        );
        $this->assertSame(
            ['id' => 'n', 'label' => 'n', 'type' => 'number', 'integer' => false, 'step' => 0.01, 'widget' => 'input'],
            $this->convert(['id' => 'n', 'type' => 'number', 'options' => ['step' => '0.01']]),
        );
    }

    public function testARangeBecomesASliderWithItsOwnDefaults(): void
    {
        $this->assertSame(
            ['id' => 'r', 'label' => 'r', 'type' => 'number', 'integer' => false, 'min' => 0, 'max' => 100, 'widget' => 'slider'],
            $this->convert(['id' => 'r', 'type' => 'range']),
        );
        $this->assertSame(
            ['id' => 'r', 'label' => 'r', 'type' => 'number', 'integer' => false, 'min' => 5, 'max' => 20, 'widget' => 'slider', 'default' => 10],
            $this->convert(['id' => 'r', 'type' => 'range', 'value' => '10', 'options' => [['min' => 5, 'max' => '20']]]),
        );
    }

    public function testCheckboxDefaultsComeFromTheOldChecked(): void
    {
        $this->assertSame(['id' => 'c', 'label' => 'c', 'type' => 'boolean', 'default' => true], $this->convert(['id' => 'c', 'type' => 'checkbox', 'checked' => true]));
        $this->assertSame(['id' => 'c', 'label' => 'c', 'type' => 'boolean'], $this->convert(['id' => 'c', 'type' => 'checkbox']));
    }

    public function testSelectRadioAndCheckboxesBecomeChoices(): void
    {
        $options = [['value' => 'a', 'label' => 'A', 'checked' => true], ['value' => 2], ['value' => 'c', 'label' => 'C', 'checked' => true]];

        $select = $this->convert(['id' => 's', 'type' => 'select', 'value' => 'c', 'options' => $options]);
        $this->assertSame(['id' => 's', 'label' => 's', 'type' => 'choice', 'multiple' => false, 'widget' => 'select', 'choices' => [['value' => 'a', 'label' => 'A'], ['value' => '2', 'label' => '2'], ['value' => 'c', 'label' => 'C']], 'default' => 'c'], $select);

        $radio = $this->convert(['id' => 'r', 'type' => 'radio', 'options' => $options]);
        $this->assertSame(['multiple' => false, 'widget' => 'radio', 'default' => 'a'], ['multiple' => $radio['multiple'], 'widget' => $radio['widget'], 'default' => $radio['default']]);

        $boxes = $this->convert(['id' => 'b', 'type' => 'checkboxes', 'options' => $options]);
        $this->assertSame(['multiple' => true, 'widget' => 'checkboxes', 'default' => ['a', 'c']], ['multiple' => $boxes['multiple'], 'widget' => $boxes['widget'], 'default' => $boxes['default']]);
    }

    public function testDataQueriesBecomeModelRelationAndHookFields(): void
    {
        $this->assertSame(
            ['id' => 'm', 'label' => 'Model', 'type' => 'model'],
            $this->convert(['id' => 'm', 'label' => 'Model', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'models', 'select' => 'id as value, name as label']]]),
        );

        $relation = $this->types->fromLegacy(
            ['id' => 'r', 'label' => 'Related', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'entries', 'select' => 'id as value, {{field:title}} as label', 'where' => "model_name = 'Article'"]]],
            ['modelIds' => ['Article' => 1]],
        );
        $this->assertNull($relation['problem']);
        $this->assertSame(['id' => 'r', 'label' => 'Related', 'type' => 'relation', 'model' => 1, 'multiple' => false], $relation['field']);
        $this->assertSame('title', $relation['labelField']);

        $this->assertSame(
            ['id' => 'h', 'label' => 'Hook', 'type' => 'hook', 'group' => 'page'],
            $this->convert(['id' => 'h', 'label' => 'Hook', 'type' => 'select', 'options' => ['type' => 'data', 'content' => 'hooks', 'group' => 'page']]),
        );
        $this->assertSame('hook', $this->convert(['id' => 'h', 'type' => 'select', 'options' => ['type' => 'data', 'content' => 'hooks']])['type']);
    }

    public function testAQueryThatCannotConvertBecomesATextFieldAndIsReported(): void
    {
        $cases = [
            'fields as label'   => ['table' => 'entries', 'select' => 'id as value, fields as label', 'where' => 'model_id = 1'],
            'no model named'    => ['table' => 'entries', 'select' => 'id as value, {{field:title}} as label'],
            'a join'            => ['table' => 'models', 'select' => 'id as value, name as label', 'joins' => []],
            'the users table'   => ['table' => 'users', 'select' => 'id as value, username as label'],
            'a limit'           => ['table' => 'entries', 'select' => 'id as value, {{field:t}} as label', 'where' => 'model_id = 1', 'limit' => 5],
            'two conditions'    => ['table' => 'entries', 'select' => 'id as value, {{field:t}} as label', 'where' => ['model_id = 1', 'id = 2']],
            'an unknown model'  => ['table' => 'entries', 'select' => 'id as value, {{field:t}} as label', 'where' => "model_name = 'Nope'"],
        ];

        foreach ($cases as $name => $content) {
            $result = $this->types->fromLegacy(['id' => 'q', 'label' => 'Q', 'type' => 'select', 'helper' => 'h', 'options' => ['type' => 'data', 'content' => $content]], ['modelIds' => ['Article' => 1]]);
            $this->assertNotNull($result['problem'], $name);
            $this->assertSame(['id' => 'q', 'label' => 'Q', 'type' => 'text', 'help' => 'h'], $result['field'], $name);
            $this->assertNull($result['labelField'], $name);
        }
    }

    public function testAnUnknownTypeBecomesTextAndAFieldWithoutAnIdIsDropped(): void
    {
        $unknown = $this->types->fromLegacy(['id' => 'd', 'label' => 'D', 'type' => 'date']);
        $this->assertStringContainsString('"date"', (string) $unknown['problem']);
        $this->assertSame(['id' => 'd', 'label' => 'D', 'type' => 'text'], $unknown['field']);

        $noId = $this->types->fromLegacy(['type' => 'text']);
        $this->assertNull($noId['field']);
        $this->assertNotNull($noId['problem']);
    }

    public function testAChoiceWithoutAListOfOptionsIsReported(): void
    {
        $result = $this->types->fromLegacy(['id' => 's', 'type' => 'select']);
        $this->assertNotNull($result['problem']);
        $this->assertSame('text', $result['field']['type']);
    }

    public function testEveryCoreTypeIsRegisteredUnderItsName(): void
    {
        $names = ['text', 'longtext', 'richtext', 'code', 'number', 'boolean', 'choice', 'datetime', 'color', 'file', 'relation', 'model', 'hook', 'hidden'];
        $this->assertSame($names, $this->types->names());
        foreach ($names as $name) {
            $this->assertSame($name, $this->types->get($name)->name());
        }
        $this->assertNull($this->types->get('nope'));
    }
}
