<?php

use App\Libraries\Upgrade\Convert\PageUrls;
use App\Modules\Blueprint;
use CodeIgniter\Test\CIUnitTestCase;
use PagingSystem\Config\PagingSystem as PagingConfig;
use PagingSystem\Libraries\PageRenderer;
use PagingSystem\Libraries\Pages;

/**
 * How a page is put together from the page, its parts, the assets and the meta tags, and the small rules around it: which
 * addresses the module leaves alone, which models fit a blueprint, and the address a title makes.
 *
 * @internal
 */
final class PageRendererTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        service('autoloader')->addNamespace('PagingSystem', ROOTPATH . 'modules/PagingSystem');
    }

    /**
     * A source of pages that holds them in memory.
     *
     * @param array<string, mixed>       $page
     * @param list<array<string, mixed>> $parts
     * @param list<array<string, mixed>> $assets
     * @param list<array<string, mixed>> $meta
     * @param array<string, string>      $lookups
     */
    private function source(array $page, array $parts = [], array $assets = [], array $meta = [], array $lookups = []): Pages
    {
        return new class($page, $parts, $assets, $meta, $lookups) extends Pages {
            public function __construct(private array $page, private array $partList, private array $assetList, private array $metaList, private array $lookups)
            {
            }

            public function find(string $path): ?array
            {
                return $path === 'about' ? $this->page : null;
            }

            public function parts(): array
            {
                return $this->partList;
            }

            public function assets(): array
            {
                return $this->assetList;
            }

            public function metaTags(): array
            {
                return $this->metaList;
            }

            public function lookup(int $modelId, string $findField, string $findValue, string $returnField): ?string
            {
                return $this->lookups["$modelId|$findField|$findValue|$returnField"] ?? null;
            }
        };
    }

    private function entry(int $id, array $fields): array
    {
        return ['id' => $id, 'fields' => $fields];
    }

    public function testThePageGoesInTheMiddleAndItsPartsAroundIt(): void
    {
        $page   = $this->entry(5, [Pages::HOOK => Pages::MAIN, Pages::TITLE => 'About us', Pages::HTML => '<p>Body</p>', Pages::CSS => 'p{margin:0}']);
        $header = $this->entry(2, [Pages::HOOK => Pages::HEADER, Pages::HTML => '<nav>Menu</nav>', Pages::CSS => 'nav{x:1}']);
        $footer = $this->entry(3, [Pages::HOOK => Pages::FOOTER, Pages::HTML => '<footer>End</footer>', Pages::CSS => '']);

        $result = (new PageRenderer($this->source($page, [$footer, $header])))->render('about', []);
        $html   = $result['html'];

        $this->assertFalse($result['dynamic']);
        $this->assertStringContainsString('<title>About us</title>', $html);
        $this->assertStringContainsString('p{margin:0}', $html);
        $this->assertStringContainsString('nav{x:1}', $html, 'The CSS of a part is in the head too.');
        $this->assertLessThan(strpos($html, '<p>Body</p>'), strpos($html, '<nav>Menu</nav>'), 'A header comes before the page.');
        $this->assertGreaterThan(strpos($html, '<p>Body</p>'), strpos($html, '<footer>End</footer>'), 'A footer comes after it.');
    }

    public function testAssetsAndMetaTagsGoWhereTheyAreMeantToGo(): void
    {
        $page = $this->entry(1, [Pages::HOOK => Pages::MAIN, Pages::TITLE => 'T', Pages::HTML => '<p>x</p>']);
        $html = (new PageRenderer($this->source(
            $page,
            [],
            [
                ['type' => 'style', 'url' => '/media/1/site.css', 'placement' => 'head'],
                ['type' => 'script', 'url' => '/media/2/site.js', 'placement' => 'body'],
            ],
            [['name' => 'description', 'content' => 'A "quoted" site']],
        )))->render('about', [])['html'];

        $this->assertMatchesRegularExpression('#<head>.*<link rel=.stylesheet. href=./media/1/site.css.>.*</head>#s', $html);
        $this->assertMatchesRegularExpression('#<body>.*<script[^>]*src=./media/2/site.js.></script>.*</body>#s', $html, 'A body script is at the end of the body.');
        $this->assertMatchesRegularExpression('#<head>.*<meta name="description" content="A &quot;quoted&quot; site">.*</head>#s', $html);
    }

    public function testAMetaTagThatNamesAnEntryIsFilledFromTheQueryString(): void
    {
        $tag  = '<meta property="og:title" data-hyper-query-model-id="9" data-hyper-query-find-field="slug" data-hyper-query-find-value="{uri-query-param}" data-hyper-query-return-field="title">';
        $page = $this->entry(1, [Pages::HOOK => Pages::MAIN, Pages::TITLE => 'T', Pages::HTML => $tag . '<p>x</p>']);
        $src  = $this->source($page, lookups: ['9|slug|first|title' => 'First post']);

        $found = (new PageRenderer($src))->render('about', ['slug' => 'first']);
        $this->assertTrue($found['dynamic'], 'A page that reads the query string must not be kept for every query.');
        $this->assertStringContainsString('content="First post"', $found['html']);
        $this->assertStringNotContainsString('data-hyper-query', $found['html']);

        $none = (new PageRenderer($src))->render('about', []);
        $this->assertStringNotContainsString('First post', $none['html']);
        $this->assertStringNotContainsString('data-hyper-query', $none['html']);
    }

    public function testAnUnknownAddressHasNoPage(): void
    {
        $this->assertNull((new PageRenderer($this->source($this->entry(1, []))))->render('missing', []));
    }

    public function testTheAddressesOfTheApplicationAreLeftToIt(): void
    {
        $config  = new PagingConfig();
        $pattern = '~' . str_replace('~', '\~', $config->routePattern()) . '~';

        foreach (['admin', 'admin/users', 'media/12/photo.png', 'api/v1/models', 'auth/login'] as $path) {
            $this->assertSame(0, preg_match($pattern, $path), "$path should be left alone");
        }
        foreach (['about', 'admin-guide', 'products/shoes', 'media-kit', ''] as $path) {
            $this->assertSame(1, preg_match($pattern, $path), "$path may be a page");
        }
    }

    public function testABlueprintFitsAModelWithTheSameFieldsOrOnesItAccepts(): void
    {
        $blueprint = new Blueprint('asset', 'Assets', '', [['id' => 'asset_url', 'type' => 'file'], ['id' => 'asset_type', 'type' => 'choice']], accepts: ['asset_url' => ['text']]);

        $this->assertTrue($blueprint->fits([['id' => 'asset_url', 'type' => 'file'], ['id' => 'asset_type', 'type' => 'choice'], ['id' => 'extra', 'type' => 'text']]));
        $this->assertTrue($blueprint->fits([['id' => 'asset_url', 'type' => 'text'], ['id' => 'asset_type', 'type' => 'choice']]), 'A link held as text fits.');
        $this->assertFalse($blueprint->fits([['id' => 'asset_url', 'type' => 'number'], ['id' => 'asset_type', 'type' => 'choice']]));
        $this->assertFalse($blueprint->fits([['id' => 'asset_url', 'type' => 'file']]), 'A missing field does not fit.');
    }

    public function testTheAttachmentFieldOffersTheHooksOfTheFrontend(): void
    {
        helper(['star', 'hyper_ui']);
        $input = (new \App\Fields\HookField())->input(null, ['id' => 'hyper_page_hook_id', 'label' => 'Attachment', 'type' => 'hook', 'group' => 'Frontend'], null);

        foreach ([Pages::MAIN, Pages::HEAD, Pages::HEADER, Pages::FOOTER] as $hook) {
            $this->assertStringContainsString('value="' . $hook . '"', $input, "$hook should be offered");
        }
    }

    public function testAMainPageWithATitleAndNoAddressGetsTheAddressItsTitleMakes(): void
    {
        $filled = PageUrls::fill([Pages::HOOK => Pages::MAIN, Pages::TITLE => 'About Us & More', Pages::URL => '']);
        $this->assertSame('about-us-more', $filled[Pages::URL]);

        $kept = [Pages::HOOK => Pages::MAIN, Pages::TITLE => 'About', Pages::URL => 'company'];
        $this->assertSame($kept, PageUrls::fill($kept));

        $part = [Pages::HOOK => Pages::FOOTER, Pages::TITLE => 'Footer', Pages::URL => null];
        $this->assertSame($part, PageUrls::fill($part), 'A part has no address.');

        $untitled = [Pages::HOOK => Pages::MAIN, Pages::TITLE => ''];
        $this->assertSame($untitled, PageUrls::fill($untitled));
    }
}
