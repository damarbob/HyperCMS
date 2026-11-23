<?php

namespace PagingSystem\Controllers;

use App\Modules\ModuleController;
use CodeIgniter\Exceptions\PageNotFoundException;
use PagingSystem\Libraries\Pages;

/**
 * The visual editor of a page. It opens one entry of the page model, and saving writes the four fields that the editor
 * owns, the HTML, the CSS, the component list and the project data, and leaves every other field of the entry alone.
 * The person must be allowed to open the pages and to change entries of that model.
 */
class Editor extends ModuleController
{
    /**
     * The editor's own fields, and the largest text each may hold. A page is a document, so the limits are generous.
     */
    private const FIELDS = [
        'html'       => ['hyper_html', 4_000_000],
        'css'        => ['hyper_css', 2_000_000],
        'components' => ['hyper_component_elements', 4_000_000],
        'project'    => ['hyper_page_project_data', 8_000_000],
    ];

    protected $helpers = ['hyper_url'];

    public function index()
    {
        $entryId = (int) $this->request->getGet('entry_id');
        if ($entryId < 1) {
            throw PageNotFoundException::forPageNotFound(lang('Admin.noEntryFound'));
        }

        $entry = $this->pageEntry($entryId);
        if ($entry === null) {
            throw PageNotFoundException::forPageNotFound(lang('Admin.noEntryFoundWithIdx', ['x' => $entryId]));
        }

        $values = json_decode((string) $entry['fields'], true);
        $values = is_array($values) && ! array_is_list($values) ? $values : [];

        // The scripts and styles of the site are loaded into the canvas, by the placement they ask for.
        $canvas = ['styles' => ['head' => [], 'body' => []], 'scripts' => ['head' => [], 'body' => []]];
        foreach ($this->pages()->assets() as $asset) {
            $canvas[$asset['type'] === 'script' ? 'scripts' : 'styles'][$asset['placement'] === 'body' ? 'body' : 'head'][] = $asset['url'];
        }

        $label  = static fn (string $key): string => lang('PagingSystem.editor_' . $key);
        $labels = [];
        foreach (['back', 'devices', 'desktop', 'tablet', 'mobileLandscape', 'mobilePortrait', 'undo', 'redo', 'save', 'unsaved', 'import', 'importHelp', 'chooseImage', 'files', 'searchBlocks', 'classMode', 'libraryMissing', 'hello', 'saveFailed', 'query_model_id', 'query_find_field', 'query_find_value', 'query_return_field'] as $key) {
            $labels[$key] = $label($key);
        }

        $model = (int) service('entryStore')->modelIdOf((int) $entry['id']);
        $name  = (string) ($values[Pages::TITLE] ?? '') !== '' ? (string) $values[Pages::TITLE] : lang('PagingSystem.editor_untitled');

        return view('PagingSystem\Views\editor', [
            'title'  => lang('PagingSystem.editor-x', ['x' => $name]),
            'editor' => [
                'entryId'   => (int) $entry['id'],
                'pageTitle' => $name,
                'back'      => base_url('admin/model/' . $model . '/' . (int) $entry['id'] . '/edit'),
                'save'      => base_url('admin/editor/' . (int) $entry['id'] . '/save'),
                'upload'    => base_url('admin/ps/api/assets/upload'),
                'csrf'      => ['name' => csrf_token(), 'hash' => csrf_hash()],
                'project'   => (string) ($values[Pages::PROJECT] ?? ''),
                'canvas'    => $canvas,
                'labels'    => $labels,
            ],
        ]);
    }

    public function save($id = 0)
    {
        $entry = $this->pageEntry((int) $id);
        if ($entry === null) {
            return $this->json(['error' => lang('PagingSystem.editorNotAllowed')], 403);
        }

        $values = json_decode((string) $entry['fields'], true);
        $values = is_array($values) && ! array_is_list($values) ? $values : [];

        foreach (self::FIELDS as $posted => [$field, $limit]) {
            $text = $this->request->getPost($posted);
            if (! is_string($text) || strlen($text) > $limit) {
                return $this->json(['error' => lang('PagingSystem.saveFailed')], 422);
            }
            // The component list and the project data are JSON that the editor reads back, so broken JSON is refused.
            if (in_array($posted, ['components', 'project'], true) && $text !== '' && json_decode($text) === null && strtolower(trim($text)) !== 'null') {
                return $this->json(['error' => lang('PagingSystem.saveFailed')], 422);
            }
            $values[$field] = $text;
        }

        try {
            service('entryStore')->update((int) $id, ['fields' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], (int) auth()->id());
        } catch (\Throwable $e) {
            log_message('error', 'A page could not be saved from the editor. {message}', ['message' => $e->getMessage()]);

            return $this->json(['error' => lang('PagingSystem.saveFailed')], 500);
        }

        return $this->json(['success' => lang('PagingSystem.savedPage')]);
    }

    /**
     * The entry, when it is an entry of the page model and the person may change it. Null otherwise.
     *
     * @return array<string, mixed>|null
     */
    private function pageEntry(int $id): ?array
    {
        $model = $this->pages()->modelId('page');
        $entry = $id > 0 ? service('entryStore')->findArray($id) : false;
        if ($model === null || ! is_array($entry) || (int) service('entryStore')->modelIdOf($id) !== $model) {
            return null;
        }

        return $this->allowed('entries.update', $model) ? $entry : null;
    }

    private function pages(): Pages
    {
        return new Pages(service('modules'), service('entryStore'), new \App\Data\WriteVersions());
    }
}
