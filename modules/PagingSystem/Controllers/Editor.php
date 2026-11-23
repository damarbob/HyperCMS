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
        $scripts = [];
        $styles  = [];
        foreach ($this->pages()->assets() as $asset) {
            if ($asset['type'] === 'script') {
                $scripts[$asset['placement']][] = $asset['url'];
            } else {
                $styles[$asset['placement']][] = $asset['url'];
            }
        }

        $this->data['entry']                = $entry;
        $this->data['mapped_entry_fields']  = $values;
        $this->data['scripts']              = $scripts;
        $this->data['styles']               = $styles;
        $this->data['saveUrl']              = base_url('admin/editor/' . (int) $entry['id'] . '/save');
        $this->data['title']                = lang('PagingSystem.editor-x', ['x' => (string) ($values[Pages::TITLE] ?? '') !== '' ? (string) $values[Pages::TITLE] : 'Untitled']);

        return render('PagingSystem\Views\editor', $this->data);
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
