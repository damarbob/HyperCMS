<?php

use App\Libraries\Upgrade\Convert\FileLinks;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The conversion of old file paths and links into library ids and `/media/{id}/{name}` links, on arrays and strings.
 *
 * @internal
 */
final class FileLinksTest extends CIUnitTestCase
{
    private FileLinks $links;

    /**
     * Paths that exist, and the library row each one becomes.
     *
     * @var array<string, array{id: int, name: string}>
     */
    private array $known = [
        'uploads/photo one.png' => ['id' => 12, 'name' => 'photo one.png'],
        'uploads/a.jpg'         => ['id' => 13, 'name' => 'a.jpg'],
        'assets/logo.svg'       => ['id' => 14, 'name' => 'logo.svg'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->links = new FileLinks();
    }

    private function resolve(): callable
    {
        return fn (string $path): ?array => $this->known[$path] ?? null;
    }

    private function hex(string $path): string
    {
        return bin2hex($path);
    }

    public function testALinkOfAnyHostBecomesTheLinkOfTheFile(): void
    {
        $text = '<img src="http://old.test/public/file-server/serve/' . $this->hex('uploads/a.jpg') . '"> and <a href="https://other.test:8443/public/file-server/serve/' . $this->hex('assets/logo.svg') . '">logo</a>';

        $result = $this->links->rewriteText($text, $this->resolve());

        $this->assertSame('<img src="/media/13/a.jpg"> and <a href="/media/14/logo.svg">logo</a>', $result['text']);
        $this->assertSame([], $result['missing']);
    }

    public function testTheNameOfTheFileIsEncodedInTheLink(): void
    {
        $result = $this->links->rewriteText('http://h/public/file-server/serve/' . $this->hex('uploads/photo one.png'), $this->resolve());

        $this->assertSame('/media/12/photo%20one.png', $result['text']);
    }

    public function testALinkInsideJsonKeepsItsEscapedSlashes(): void
    {
        $json = json_encode(['assets' => [['src' => 'http://old.test/public/file-server/serve/' . $this->hex('uploads/a.jpg')]]]);
        $this->assertStringContainsString('\\/public\\/file-server\\/serve\\/', $json);

        $result = $this->links->rewriteText($json, $this->resolve());

        $this->assertStringContainsString('"src":"\\/media\\/13\\/a.jpg"', $result['text']);
        $this->assertSame('/media/13/a.jpg', json_decode($result['text'], true)['assets'][0]['src']);
    }

    public function testALinkToAFileThatIsNotThereIsLeftAndListed(): void
    {
        $text   = '<img src="http://old.test/public/file-server/serve/' . $this->hex('uploads/gone.png') . '">';
        $result = $this->links->rewriteText($text, $this->resolve());

        $this->assertSame($text, $result['text']);
        $this->assertSame(['uploads/gone.png'], $result['missing']);
    }

    public function testOtherLinksAndPlainTextAreNotTouched(): void
    {
        $text = '<a href="https://example.com/file-server/serve/zzz">x</a> <img src="/media/3/x.png"> <img src="http://h/public/file-server/serve/abc">';

        $result = $this->links->rewriteText($text, $this->resolve());

        $this->assertSame($text, $result['text'], 'A link whose path is not hexadecimal of a known file is kept.');
    }

    public function testRunningTheConversionAgainChangesNothing(): void
    {
        $once  = $this->links->rewriteText('<img src="http://h/public/file-server/serve/' . $this->hex('uploads/a.jpg') . '">', $this->resolve())['text'];
        $twice = $this->links->rewriteText($once, $this->resolve());

        $this->assertSame($once, $twice['text']);
    }

    public function testAFileFieldTurnsPathsAndLinksIntoIds(): void
    {
        $link = 'http://h/public/file-server/serve/' . $this->hex('assets/logo.svg');

        $this->assertSame(13, $this->links->fieldValue('uploads/a.jpg', false, $this->resolve())['value']);
        $this->assertSame(14, $this->links->fieldValue($link, false, $this->resolve())['value']);
        $this->assertSame([13, 14], $this->links->fieldValue(['uploads/a.jpg', $link], true, $this->resolve())['value']);
        $this->assertNull($this->links->fieldValue('', false, $this->resolve())['value']);
        $this->assertSame([], $this->links->fieldValue(null, true, $this->resolve())['value']);
        $this->assertSame([13], $this->links->fieldValue(13, true, $this->resolve())['value'], 'An id that is already there is kept.');
        $this->assertSame(13, $this->links->fieldValue(13, false, $this->resolve())['value']);
    }

    public function testAFileFieldKeepsWhatItCannotConvertAndSaysWhy(): void
    {
        $result = $this->links->fieldValue(['uploads/a.jpg', 'uploads/gone.png', 'https://cdn.test/x.png'], true, $this->resolve());

        $this->assertSame([13, 'uploads/gone.png', 'https://cdn.test/x.png'], $result['value']);
        $this->assertCount(2, $result['problems']);
        $this->assertStringContainsString('uploads/gone.png', $result['problems'][0]);
        $this->assertStringContainsString('another host', $result['problems'][1]);
    }

    public function testAnEntryHasItsFileFieldsAndItsTextLinksRewritten(): void
    {
        $definitions = [
            ['id' => 'cover', 'type' => 'file'],
            ['id' => 'gallery', 'type' => 'file', 'multiple' => true],
            ['id' => 'body', 'type' => 'richtext'],
            ['id' => 'project', 'type' => 'code'],
            ['id' => 'tags', 'type' => 'choice', 'multiple' => true],
        ];
        $fields = [
            'cover'   => 'uploads/a.jpg',
            'gallery' => ['uploads/a.jpg', 'uploads/missing.png'],
            'body'    => '<img src="http://h/public/file-server/serve/' . $this->hex('assets/logo.svg') . '">',
            'project' => json_encode(['src' => 'http://h/public/file-server/serve/' . $this->hex('uploads/a.jpg')]),
            'tags'    => ['a', 'b'],
            'other'   => 'a value the model does not name',
        ];

        $result = $this->links->convert($fields, $definitions, $this->resolve());

        $this->assertSame(13, $result['fields']['cover']);
        $this->assertSame([13, 'uploads/missing.png'], $result['fields']['gallery']);
        $this->assertSame('<img src="/media/14/logo.svg">', $result['fields']['body']);
        $this->assertSame('/media/13/a.jpg', json_decode($result['fields']['project'], true)['src']);
        $this->assertSame(['a', 'b'], $result['fields']['tags']);
        $this->assertSame('a value the model does not name', $result['fields']['other']);
        $this->assertSame([['field' => 'gallery', 'problem' => 'the file uploads/missing.png is not in the public folder, or could not be copied']], $result['problems']);

        $again = $this->links->convert($result['fields'], $definitions, $this->resolve());
        $this->assertSame($result['fields'], $again['fields']);
    }
}
