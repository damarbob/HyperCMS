<?php

use App\Fields\FieldTypes;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\StubFiles;

/**
 * What each type draws in a list and in the entry form, and what it offers to filter by.
 *
 * @internal
 */
final class FieldRenderingTest extends CIUnitTestCase
{
    private FieldTypes $types;

    protected function setUp(): void
    {
        parent::setUp();
        helper('hyper_ui');
        service('language')->setLocale('en');
        $this->types = FieldTypes::core();
        Services::injectMock('files', new StubFiles([12 => ['id' => 12, 'name' => 'photo one.png']]));
    }

    protected function tearDown(): void
    {
        Services::resetSingle('files');
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function cell(array $definition, mixed $value): string
    {
        return $this->types->get($definition['type'])->cell($value, $definition + ['label' => 'X']);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function input(array $definition, mixed $value, ?string $error = null): string
    {
        return $this->types->get($definition['type'])->input($value, $definition + ['label' => 'X'], $error);
    }

    public function testAnEmptyValueIsMarkedAsNotSetInEveryList(): void
    {
        foreach (['text', 'longtext', 'number', 'datetime', 'color', 'choice', 'file'] as $type) {
            $this->assertStringContainsString('Not set', $this->cell(['id' => 'a', 'type' => $type], null), $type);
        }
        $this->assertStringContainsString('Not set', $this->cell(['id' => 'a', 'type' => 'text'], ''));
        $this->assertStringContainsString('Not set', $this->cell(['id' => 'a', 'type' => 'choice', 'multiple' => true], []));
    }

    public function testTextIsEscapedAndLinksAreMadeSafe(): void
    {
        $this->assertSame('&lt;b&gt;Bold&lt;/b&gt;', $this->cell(['id' => 'a', 'type' => 'text'], '<b>Bold</b>'));
        $this->assertStringContainsString('href="mailto:a@b.test"', $this->cell(['id' => 'a', 'type' => 'text', 'format' => 'email'], 'a@b.test'));
        $this->assertStringContainsString('rel="noopener noreferrer"', $this->cell(['id' => 'a', 'type' => 'text', 'format' => 'url'], 'https://x.test/a'));
        $this->assertStringNotContainsString('<a ', $this->cell(['id' => 'a', 'type' => 'text', 'format' => 'url'], 'javascript:alert(1)'));
        $this->assertStringNotContainsString('secret', $this->cell(['id' => 'a', 'type' => 'text', 'format' => 'password'], 'secret'));
    }

    public function testLongTextsAreCutAndRichTextLosesItsMarkup(): void
    {
        $this->assertStringContainsString('…', $this->cell(['id' => 'a', 'type' => 'longtext'], str_repeat('word ', 60)));
        $this->assertSame('Hello &amp; welcome', $this->cell(['id' => 'a', 'type' => 'richtext'], '<p>Hello &amp; <b>welcome</b></p>'));
        $this->assertStringContainsString('<code class="mono">', $this->cell(['id' => 'a', 'type' => 'code'], '<div>x</div>'));
        $this->assertStringContainsString('&lt;div&gt;', $this->cell(['id' => 'a', 'type' => 'code'], '<div>x</div>'));
    }

    public function testNumbersShowWithoutTrailingZeros(): void
    {
        $this->assertSame('12.5', $this->cell(['id' => 'a', 'type' => 'number'], 12.5));
        $this->assertSame('10', $this->cell(['id' => 'a', 'type' => 'number'], 10));
        $this->assertSame('0.01', $this->cell(['id' => 'a', 'type' => 'number'], 0.01));
        $this->assertSame('0', $this->cell(['id' => 'a', 'type' => 'number'], 0));
    }

    public function testBooleansChoicesColoursAndDatesShowForPeople(): void
    {
        $this->assertStringContainsString('Yes', $this->cell(['id' => 'a', 'type' => 'boolean'], true));
        $this->assertStringContainsString('No', $this->cell(['id' => 'a', 'type' => 'boolean'], false));

        $choice = ['id' => 'a', 'type' => 'choice', 'multiple' => true, 'choices' => [['value' => 'news', 'label' => 'News'], ['value' => 'tech', 'label' => 'Tech']]];
        $this->assertStringContainsString('>News<', $this->cell($choice, ['news', 'old']));
        $this->assertStringContainsString('>old<', $this->cell($choice, ['news', 'old']), 'A value that is no longer a choice shows as it is.');

        $this->assertStringContainsString('style="background:#aabbcc"', $this->cell(['id' => 'a', 'type' => 'color'], '#aabbcc'));
        $this->assertStringNotContainsString('style=', $this->cell(['id' => 'a', 'type' => 'color'], 'red;x'));

        $date = $this->cell(['id' => 'a', 'type' => 'datetime'], '2026-02-02 14:05:00');
        $this->assertStringContainsString('datetime="2026-02-02T14:05:00"', $date);
        $this->assertStringContainsString('2026', $date);
    }

    public function testFilesShowAsLinksToTheLibrary(): void
    {
        $cell = $this->cell(['id' => 'a', 'type' => 'file', 'multiple' => true], [12, 99, 'uploads/old.png']);

        $this->assertStringContainsString('media/12/photo%20one.png"', $cell);
        $this->assertStringContainsString('>photo one.png<', $cell);
        $this->assertStringContainsString('File 99 is missing', $cell, 'A file that was deleted is marked, not dropped.');
        $this->assertStringContainsString('uploads/old.png', $cell, 'A value the upgrade left as text is shown as it is.');
        $this->assertStringNotContainsString('<script', $this->cell(['id' => 'a', 'type' => 'file'], '"><script>'));
    }

    public function testATextInputWiresTheLabelTheErrorAndTheName(): void
    {
        $html = $this->input(['id' => 'title', 'type' => 'text', 'required' => true, 'help' => 'Shown in lists'], 'Hello "x"', 'Title is required.');

        $this->assertStringContainsString('name="fields[title]"', $html);
        $this->assertStringContainsString('id="f-title"', $html);
        $this->assertStringContainsString('value="Hello &quot;x&quot;"', $html);
        $this->assertStringContainsString('aria-describedby="f-title-help f-title-error"', $html);
        $this->assertStringContainsString('Title is required.', $html);
        $this->assertStringContainsString('<label for="f-title">', $html);
        $this->assertStringContainsString(' required', $html);
    }

    public function testAHelpLineKeepsTheBaseUrlPlaceholder(): void
    {
        $html = $this->input(['id' => 'a', 'type' => 'text', 'help' => 'See [base_url]docs'], '');

        $this->assertStringNotContainsString('[base_url]', $html);
        $this->assertStringContainsString(base_url() . 'docs', html_entity_decode($html));
    }

    public function testAPasswordIsNeverWrittenBackIntoTheForm(): void
    {
        $html = $this->input(['id' => 'p', 'type' => 'text', 'format' => 'password', 'required' => true], 'secret');

        $this->assertStringNotContainsString('secret', $html);
        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
        $this->assertStringNotContainsString(' required', $html);
    }

    public function testTextAreasHandTheirIslandToTheScript(): void
    {
        $this->assertStringContainsString('<textarea class="textarea"', $this->input(['id' => 'a', 'type' => 'longtext'], 'x'));
        $this->assertStringNotContainsString('data-island', $this->input(['id' => 'a', 'type' => 'longtext'], 'x'));
        $this->assertStringContainsString('data-island="richtext"', $this->input(['id' => 'a', 'type' => 'richtext'], '<p>x</p>'));

        $code = $this->input(['id' => 'a', 'type' => 'code', 'language' => 'html'], '<b>x</b>');
        $this->assertStringContainsString('data-island="code"', $code);
        $this->assertStringContainsString('data-language="html"', $code);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $code);
    }

    public function testNumberInputsCarryTheirLimitsAndStep(): void
    {
        $whole = $this->input(['id' => 'n', 'type' => 'number', 'integer' => true, 'min' => 1, 'max' => 9], 5);
        $this->assertStringContainsString('type="number"', $whole);
        $this->assertStringContainsString('step="1"', $whole);
        $this->assertStringContainsString('min="1"', $whole);
        $this->assertStringContainsString('max="9"', $whole);
        $this->assertStringContainsString('value="5"', $whole);

        $free = $this->input(['id' => 'n', 'type' => 'number', 'integer' => false], 12.5);
        $this->assertStringContainsString('step="any"', $free);
        $this->assertStringContainsString('value="12.5"', $free);

        $slider = $this->input(['id' => 'n', 'type' => 'number', 'widget' => 'slider', 'min' => 0, 'max' => 100], 40);
        $this->assertStringContainsString('type="range"', $slider);
    }

    public function testABooleanPostsZeroWhenUnticked(): void
    {
        $off = $this->input(['id' => 'on', 'type' => 'boolean'], false);
        $this->assertStringContainsString('<input type="hidden" name="fields[on]" value="0">', $off);
        $this->assertStringContainsString('type="checkbox" value="1"', $off);
        $this->assertStringNotContainsString(' checked', $off);

        $this->assertStringContainsString(' checked', $this->input(['id' => 'on', 'type' => 'boolean'], true));
        $this->assertStringContainsString('<fieldset', $off);
    }

    public function testChoicesDrawASelectRadioButtonsOrCheckBoxes(): void
    {
        $choices = [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']];

        $select = $this->input(['id' => 'c', 'type' => 'choice', 'widget' => 'select', 'choices' => $choices], 'b');
        $this->assertStringContainsString('<option value="b" selected>B</option>', $select);
        $this->assertStringContainsString('<option value="">Choose one</option>', $select);
        $this->assertStringNotContainsString('<option value="">', $this->input(['id' => 'c', 'type' => 'choice', 'required' => true, 'choices' => $choices], 'b'));

        $radio = $this->input(['id' => 'c', 'type' => 'choice', 'widget' => 'radio', 'choices' => $choices], 'a');
        $this->assertStringContainsString('type="radio"', $radio);
        $this->assertStringContainsString('name="fields[c]"', $radio);
        $this->assertStringContainsString('<fieldset', $radio);

        $boxes = $this->input(['id' => 'c', 'type' => 'choice', 'multiple' => true, 'widget' => 'checkboxes', 'choices' => $choices], ['a']);
        $this->assertStringContainsString('name="fields[c][]"', $boxes);
        $this->assertSame(1, substr_count($boxes, ' checked'));
        $this->assertStringContainsString('<input type="hidden" name="fields[c][]" value="">', $boxes, 'An empty list still posts the field.');
    }

    public function testDatesColoursAndFilesDrawTheirInputs(): void
    {
        $date = $this->input(['id' => 'd', 'type' => 'datetime'], '2026-02-02 14:05:00');
        $this->assertStringContainsString('type="datetime-local" value="2026-02-02T14:05"', $date);
        $this->assertStringNotContainsString('step=', $date);

        $withSeconds = $this->input(['id' => 'd', 'type' => 'datetime'], '2026-02-02 14:05:09');
        $this->assertStringContainsString('value="2026-02-02T14:05:09"', $withSeconds);
        $this->assertStringContainsString('step="1"', $withSeconds);

        $this->assertStringContainsString('type="color" value="#aabbcc"', $this->input(['id' => 'c', 'type' => 'color'], '#aabbcc'));
        $this->assertStringContainsString('value="#000000"', $this->input(['id' => 'c', 'type' => 'color'], null));

        $file = $this->input(['id' => 'f', 'type' => 'file'], 12);
        $this->assertStringContainsString('name="fields[f]"', $file);
        $this->assertStringContainsString('<option value="12" selected', $file);
        $this->assertStringContainsString('>photo one.png</option>', $file);
        $this->assertStringContainsString('<option value="">', $file, 'A single optional file can be cleared.');
        $this->assertStringNotContainsString('<input', $file, 'The ids are held by a select, which the form posts.');

        $many = $this->input(['id' => 'f', 'type' => 'file', 'multiple' => true], [12, 99]);
        $this->assertStringContainsString('name="fields[f][]"', $many);
        $this->assertStringContainsString('File 99 is missing', $many);
    }

    public function testAHiddenFieldPostsItsValueAndShowsNoLabel(): void
    {
        $html = $this->input(['id' => 'h', 'type' => 'hidden', 'default' => 'x'], null);

        $this->assertSame('<input type="hidden" value="x" name="fields[h]">', $html);
    }

    public function testEachTypeOffersTheFiltersItsIndexCanAnswer(): void
    {
        $ops = fn (array $definition): array => array_column($this->types->get($definition['type'])->filters($definition), 'op');

        $this->assertSame(['prefix', 'eq'], $ops(['type' => 'text']));
        $this->assertSame(['gte', 'lte'], $ops(['type' => 'number']));
        $this->assertSame(['gte', 'lte'], $ops(['type' => 'datetime']));
        $this->assertSame(['eq'], $ops(['type' => 'choice', 'multiple' => false, 'choices' => [['value' => 'a', 'label' => 'A']]]));
        $this->assertSame([], $ops(['type' => 'choice', 'multiple' => true]));
        foreach (['longtext', 'richtext', 'code', 'boolean', 'file', 'hidden'] as $type) {
            $this->assertSame([], $ops(['type' => $type]), $type);
        }
    }
}
