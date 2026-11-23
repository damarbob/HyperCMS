<?php

namespace PagingSystem\Libraries;

use DOMDocument;
use DOMXPath;
use Masterminds\HTML5;

/**
 * Makes the HTML of a page from the page, the parts that sit on every page, and the assets and meta tags of the site.
 *
 * The parts go where their attachment says: the head, above the page, or below it. The page keeps its own order, and the
 * output is the same document that the page module made before it used named hooks. The meta tags that name a model, a
 * field and a value are filled from the entries, which is why a page that has them depends on the address it was asked for.
 */
final class PageRenderer
{
    private const DYNAMIC = ['data-hyper-query-model-id', 'data-hyper-query-find-field', 'data-hyper-query-find-value', 'data-hyper-query-return-field'];

    public function __construct(private readonly Pages $pages)
    {
    }

    /**
     * @param array<string, mixed> $query The query string of the request, for the meta tags that read it.
     *
     * @return array{html: string, dynamic: bool}|null Null when no page has the path.
     */
    public function render(string $path, array $query): ?array
    {
        $page = $this->pages->find($path);
        if ($page === null) {
            return null;
        }

        $head   = '';
        $header = '';
        $main   = '';
        $footer = '';

        foreach ($this->pages->metaTags() as $tag) {
            $head .= '<meta name="' . esc($tag['name'], 'attr') . '" content="' . esc($tag['content'], 'attr') . '">';
        }

        foreach ($this->pages->assets() as $asset) {
            $tag = $asset['type'] === 'style'
                ? "<link rel='stylesheet' href='" . esc($asset['url'], 'attr') . "'>"
                : "<script type='text/javascript' src='" . esc($asset['url'], 'attr') . "'></script>";

            match ($asset['placement']) {
                'head'  => $head .= $tag,
                'body'  => $footer .= $tag,
                default => $main .= $tag,
            };
        }

        $dynamic = false;
        $entries = array_merge($this->pages->parts(), [$page]);
        usort($entries, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        foreach ($entries as $entry) {
            $fields = $entry['fields'];
            $hook   = (string) ($fields[Pages::HOOK] ?? '');
            $css    = (string) ($fields[Pages::CSS] ?? '');
            $html   = (string) ($fields[Pages::HTML] ?? '');

            if ($entry['id'] === $page['id']) {
                $head .= '<title>' . esc((string) ($fields[Pages::TITLE] ?? '')) . '</title>';
                $head .= "<style>$css</style>";

                $dom = $this->html5()->loadHTML($html);
                $dynamic = $this->fillMeta($dom, $query) || $dynamic;
                $main .= $this->html5()->saveHTML($dom);

                continue;
            }

            $head .= "<style>$css</style>";
            match ($hook) {
                Pages::HEAD   => $head .= $html,
                Pages::HEADER => $header .= $html,
                Pages::FOOTER => $footer .= $html,
                default       => null,
            };
        }

        $document = "<html>\n\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"initial-scale=1, width=device-width\" />\n    $head\n</head>\n\n<body>\n    $header\n    $main\n    $footer\n</body>\n\n</html>";

        return ['html' => $this->arrange($document), 'dynamic' => $dynamic];
    }

    private function html5(): HTML5
    {
        return new HTML5(['disable_html_ns' => true, 'preserve_whitespace' => true]);
    }

    /**
     * Fill the meta tags that ask for the value of a field of some entry, and take their helper attributes away. A tag whose
     * value comes from the query string stays empty when the request has no such parameter.
     *
     * @param array<string, mixed> $query
     *
     * @return bool Whether the document had such a tag.
     */
    private function fillMeta(DOMDocument $dom, array $query): bool
    {
        $had = false;
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            foreach (self::DYNAMIC as $attribute) {
                if (! $meta->hasAttribute($attribute)) {
                    continue 2;
                }
            }

            $had   = true;
            $model = (int) $meta->getAttribute('data-hyper-query-model-id');
            $field = $meta->getAttribute('data-hyper-query-find-field');
            $value = $meta->getAttribute('data-hyper-query-find-value');
            $take  = $meta->getAttribute('data-hyper-query-return-field');
            if ($value === '{uri-query-param}') {
                $value = isset($query[$field]) && is_scalar($query[$field]) ? (string) $query[$field] : '';
            }

            $found = $value === '' || $model < 1 ? null : $this->pages->lookup($model, $field, $value, $take);
            if ($found !== null) {
                $meta->setAttribute('content', $found);
            }
            foreach (self::DYNAMIC as $attribute) {
                $meta->removeAttribute($attribute);
            }
        }

        return $had;
    }

    /**
     * Put every meta tag in the head and every script that is not in the head at the end of the body, whatever the editor
     * left in the page.
     */
    private function arrange(string $document): string
    {
        $html5 = $this->html5();
        $dom   = $html5->loadHTML($document);

        $head = $dom->getElementsByTagName('head')->item(0);
        $body = $dom->getElementsByTagName('body')->item(0);

        if (! $head) {
            $head = $dom->createElement('head');
            $root = $dom->getElementsByTagName('html')->item(0) ?: $dom->appendChild($dom->createElement('html'));
            $root->insertBefore($head, $body ?? null);
        }
        if (! $body) {
            $body = $dom->createElement('body');
            $dom->documentElement->appendChild($body);
        }

        $xpath = new DOMXPath($dom);
        $nodes = [];
        foreach ($xpath->query('//meta') as $node) {
            $nodes[] = [$node, true, false];
        }
        foreach ($xpath->query('//script') as $node) {
            $nodes[] = [$node, false, $node->parentNode->nodeName === 'head'];
        }

        foreach ($nodes as [$node, $isMeta, $inHead]) {
            if ($isMeta) {
                if ($node->parentNode !== $head) {
                    $node->parentNode->removeChild($node);
                    $head->appendChild($node);
                }
            } elseif (! $inHead && $node->parentNode !== $body) {
                $node->parentNode->removeChild($node);
                $body->appendChild($node);
            }
        }

        return $html5->saveHTML($dom);
    }
}
