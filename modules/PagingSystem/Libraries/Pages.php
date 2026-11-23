<?php

namespace PagingSystem\Libraries;

use App\Data\Entries\FieldFilters;
use App\Data\Entries\ScanLimitExceeded;
use App\Data\EntryStore;
use App\Data\WriteVersions;
use App\Modules\Modules;
use StarDust\Read\SortDirection;
use StarDust\Read\SortSpec;

/**
 * Reads the pages, the page parts, the assets and the meta tags that the page module serves.
 *
 * The three models are the ones chosen in the settings of the module, and nothing here looks through the models to find
 * them. A page is found by its address through one indexed read of the `hyper_page_url` field, and the parts that sit on
 * every page are found by one indexed read of `hyper_page_hook_id`. While a field is still being indexed, which the
 * tick finishes after a model is changed, the same question is answered by reading the entries of the model instead.
 */
class Pages
{
    public const MAIN   = 'frontend:main';
    public const HEAD   = 'frontend:head';
    public const HEADER = 'frontend:header';
    public const FOOTER = 'frontend:footer';

    public const URL   = 'hyper_page_url';
    public const HOOK  = 'hyper_page_hook_id';
    public const TITLE = 'hyper_title';
    public const HTML  = 'hyper_html';
    public const CSS   = 'hyper_css';
    public const PROJECT = 'hyper_page_project_data';

    /**
     * The most entries one indexed read of parts or meta tags brings.
     */
    private const LIMIT = 200;

    public function __construct(private readonly Modules $modules, private readonly EntryStore $entries, private readonly WriteVersions $versions)
    {
    }

    /**
     * The model chosen for `page`, `meta` or `asset`, or null when none is chosen.
     */
    public function modelId(string $key): ?int
    {
        return $this->modules->modelOf('PagingSystem', $key);
    }

    /**
     * A number that changes whenever anything is saved, so a page kept under it is never older than the data.
     */
    public function version(): int
    {
        return $this->versions->global();
    }

    /**
     * The pages of the site that were edited last, newest first. A part that sits on every page is not one of them.
     *
     * @return list<array{id: int, title: string, path: string, edited: string}>
     */
    public function latest(int $limit): array
    {
        $model = $this->modelId('page');
        if ($model === null) {
            return [];
        }

        // Parts and pages share a model, so a few more than asked for are read to find enough pages among them.
        $rows = $this->entries->dataTable([
            'draw'     => 1,
            'start'    => 0,
            'length'   => max(20, $limit * 4),
            'model_id' => $model,
            'order'    => [['column' => 0, 'dir' => 'desc']],
            'columns'  => [['data' => 'date_modified']],
        ])['data'];

        $pages = [];
        foreach ($rows as $row) {
            $page = $this->present($row);
            if (($page['fields'][self::HOOK] ?? null) !== self::MAIN) {
                continue;
            }

            $title   = trim((string) ($page['fields'][self::TITLE] ?? ''));
            $pages[] = [
                'id'     => $page['id'],
                'title'  => $title !== '' ? $title : '#' . $page['id'],
                'path'   => trim((string) ($page['fields'][self::URL] ?? '')),
                'edited' => (string) ($row['date_modified'] ?? ''),
            ];
            if (count($pages) >= $limit) {
                break;
            }
        }

        return $pages;
    }

    /**
     * The page whose address is the path, or null.
     *
     * @return array{id: int, fields: array<string, mixed>}|null
     */
    public function find(string $path): ?array
    {
        $model = $this->modelId('page');
        if ($model === null) {
            return null;
        }

        foreach ($this->where($model, self::URL, [$path]) as $page) {
            if (($page['fields'][self::HOOK] ?? null) === self::MAIN) {
                return $page;
            }
        }

        return null;
    }

    /**
     * The pages that are not a page of their own but a part of every page, in the order of their ids.
     *
     * @return list<array{id: int, fields: array<string, mixed>}>
     */
    public function parts(): array
    {
        $model = $this->modelId('page');
        if ($model === null) {
            return [];
        }

        $parts = $this->where($model, self::HOOK, [self::HEAD, self::HEADER, self::FOOTER]);
        usort($parts, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $parts;
    }

    /**
     * The scripts and styles that every page loads.
     *
     * @return list<array{type: string, url: string, placement: string}>
     */
    public function assets(): array
    {
        $found = [];
        foreach ($this->all('asset') as $asset) {
            $type = (string) ($asset['fields']['asset_type'] ?? '');
            $url  = $this->urlOf($asset['fields']['asset_url'] ?? null);
            if ($url === '' || ! in_array($type, ['script', 'style'], true)) {
                continue;
            }
            $found[] = ['type' => $type, 'url' => $url, 'placement' => (string) ($asset['fields']['asset_placement'] ?? 'head')];
        }

        return $found;
    }

    /**
     * The meta tags that every page carries.
     *
     * @return list<array{name: string, content: string}>
     */
    public function metaTags(): array
    {
        $found = [];
        foreach ($this->all('meta') as $meta) {
            $name = trim((string) ($meta['fields']['meta_name'] ?? ''));
            if ($name !== '') {
                $found[] = ['name' => $name, 'content' => (string) ($meta['fields']['meta_content'] ?? '')];
            }
        }

        return $found;
    }

    /**
     * The first value of a field among the entries of a model whose field equals a value. The dynamic meta tags of a page use
     * it, and the page names the model, the field to look in, the value and the field to take.
     */
    public function lookup(int $modelId, string $findField, string $findValue, string $returnField): ?string
    {
        foreach ($this->where($modelId, $findField, [$findValue], 1) as $entry) {
            if (isset($entry['fields'][$returnField]) && is_scalar($entry['fields'][$returnField])) {
                return (string) $entry['fields'][$returnField];
            }
        }

        return null;
    }

    /**
     * Every entry of the model chosen for a key.
     *
     * @return list<array{id: int, fields: array<string, mixed>}>
     */
    private function all(string $key): array
    {
        $model = $this->modelId($key);
        if ($model === null) {
            return [];
        }

        $rows = [];
        foreach ($this->entries->ofModels([$model]) as $row) {
            if ((int) ($row['model_id'] ?? 0) === $model) {
                $rows[] = $this->present($row);
            }
        }
        usort($rows, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $rows;
    }

    /**
     * The entries of a model whose field holds one of the values. The index answers when the field is indexed, and the
     * entries are read otherwise.
     *
     * @param list<string> $values
     *
     * @return list<array{id: int, fields: array<string, mixed>}>
     */
    private function where(int $modelId, string $field, array $values, int $limit = self::LIMIT): array
    {
        $indexed = $this->entries->indexedFields($modelId);
        if (isset($indexed[$field])) {
            $filter = FieldFilters::parse([$field => count($values) === 1 ? ['eq' => $values[0]] : ['in' => $values]], $indexed);
            $page   = $this->entries->fieldPage($modelId, [], SortSpec::byId(SortDirection::Asc), $filter, $limit, 1, 1);

            return array_map($this->present(...), $page['data']);
        }

        try {
            $rows = $this->entries->ofModels([$modelId]);
        } catch (ScanLimitExceeded) {
            return [];
        }

        $found = [];
        foreach ($rows as $row) {
            if ((int) ($row['model_id'] ?? 0) !== $modelId) {
                continue;
            }
            $entry = $this->present($row);
            if (in_array($entry['fields'][$field] ?? null, $values, true)) {
                $found[] = $entry;
            }
            if (count($found) >= $limit) {
                break;
            }
        }

        return $found;
    }

    /**
     * An entry row as its id and its values.
     *
     * @param array<string, mixed> $row
     *
     * @return array{id: int, fields: array<string, mixed>}
     */
    private function present(array $row): array
    {
        $fields = json_decode((string) ($row['fields'] ?? ''), true);

        return ['id' => (int) $row['id'], 'fields' => is_array($fields) && ! array_is_list($fields) ? $fields : []];
    }

    /**
     * The address of an asset, which is the id of a library file or a link.
     */
    private function urlOf(mixed $value): string
    {
        if (is_int($value)) {
            $file = service('files')->find($value);

            return $file === null ? '' : '/' . \App\Data\Files::path($file);
        }

        return is_string($value) ? trim($value) : '';
    }
}
