<?php

use App\Libraries\EntryCsv;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\StubFiles;

/**
 * The text of a CSV export.
 *
 * @internal
 */
final class EntryCsvTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
    }

    public function testValuesBecomePlainText(): void
    {
        $this->assertSame('', EntryCsv::value(null, ['type' => 'text']));
        $this->assertSame('12.5', EntryCsv::value(12.5, ['type' => 'number']));
        $this->assertSame('Yes', EntryCsv::value(true, ['type' => 'boolean']));
        $this->assertSame('No', EntryCsv::value(false, ['type' => 'boolean']));
        $this->assertSame('Hello world', EntryCsv::value('<p>Hello <b>world</b></p>', ['type' => 'richtext']));
    }

    public function testFilesShowTheirLinks(): void
    {
        Services::injectMock('files', new StubFiles([12 => ['id' => 12, 'name' => 'photo.png']]));

        try {
            $text = EntryCsv::value([12, 99], ['type' => 'file', 'multiple' => true]);
        } finally {
            Services::resetSingle('files');
        }

        $this->assertStringContainsString('media/12/photo.png, 99', $text, 'A file that is gone keeps its id.');
    }

    public function testChoicesShowTheirLabels(): void
    {
        $definition = ['type' => 'choice', 'choices' => [['value' => 'news', 'label' => 'News'], ['value' => 'tech', 'label' => 'Tech']]];

        $this->assertSame('News, Tech', EntryCsv::value(['news', 'tech'], $definition));
        $this->assertSame('News, gone', EntryCsv::value(['news', 'gone'], $definition));
        $this->assertSame('Tech', EntryCsv::value('tech', $definition));
    }

    public function testACellThatCouldBeAFormulaIsQuoted(): void
    {
        foreach (['=1+1', '+1', '-1', '@sum', "\tx"] as $text) {
            $this->assertSame("'" . $text, EntryCsv::value($text, ['type' => 'text']), $text);
        }
        $this->assertSame('a=b', EntryCsv::value('a=b', ['type' => 'text']));
    }

    public function testTheFileHasABomAndQuotesWhatNeedsIt(): void
    {
        $csv = EntryCsv::write(['Title', 'Note'], [['A, B', 'He said "hi"'], ['Plain', '']]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("Title,Note\n", $csv);
        $this->assertStringContainsString('"A, B","He said ""hi"""', $csv);
    }
}
