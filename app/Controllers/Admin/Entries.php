<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Fields\FieldTypes;
use App\Libraries\AllEntriesPage;
use App\Libraries\ModelListPage;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * The entry form and the actions on entries: create, save, move to the trash, restore and empty the trash.
 *
 * Every action names the model in its address, and every entry it touches is checked to belong to that model, so the
 * permission checked for the model is the one that decides for the entry.
 */
class Entries extends AdminController
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * The list of every entry of the models the person may open.
     */
    public function index()
    {
        $per  = (int) (service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 25);
        $data = (new AllEntriesPage(FieldTypes::core()))->build($this->request->getGet(), $per);

        // A module may change where the New entry links go, as the paging system does for its page models.
        $data = $this->hooks->filter(hook('Backend.controller:entries:index:data'), $data);

        if (Model::wantsTheList($this->request)) {
            return view('admin/partials/all_results', $data);
        }

        $this->data = array_merge($this->data, $data, ['title' => lang('Entries.allTitle'), 'crumbs' => [[lang('Entries.allTitle'), null]]]);

        return view('admin/entries', $this->data);
    }

    /* The form ---------------------------------------------------------------------------------------------- */

    public function new($modelId)
    {
        $model = $this->model($modelId, 'entries.create');
        if (! is_array($model)) {
            return $model;
        }

        $values = [];
        foreach ($this->definition($model) as $field) {
            $values[$field['id']] = $field['default'] ?? null;
        }

        return $this->form($model, null, $values, []);
    }

    public function edit($modelId, $id)
    {
        $model = $this->model($modelId, 'entries.read');
        if (! is_array($model)) {
            return $model;
        }
        $entry = $this->entry($model, $id);
        if (! is_array($entry)) {
            return $entry;
        }

        return $this->form($model, $entry, $this->valuesOf($entry), []);
    }

    public function create($modelId)
    {
        $model = $this->model($modelId, 'entries.create');
        if (! is_array($model)) {
            return $model;
        }

        [$values, $errors] = $this->read($model, [], $this->posted());
        if ($errors === []) {
            try {
                $id = service('entryStore')->create(['model_id' => (int) $modelId, 'fields' => json_encode($values, self::JSON_FLAGS)], (int) auth()->user()->id);
            } catch (InvalidArgumentException | RuntimeException $e) {
                return $this->form($model, null, $values, [], $e->getMessage(), 422);
            }

            $edit = base_url('admin/model/' . (int) $modelId . '/' . $id . '/edit');
            session()->setFlashdata('success', lang('Entries.created'));

            // htmx follows this header, and a plain form post follows the redirect.
            return $this->isHtmx() ? $this->response->setHeader('HX-Redirect', $edit)->setStatusCode(204) : redirect()->to($edit);
        }

        return $this->form($model, null, $values, $errors, lang('Entries.fixErrors'), 422);
    }

    public function update($modelId, $id)
    {
        $model = $this->model($modelId, 'entries.update');
        if (! is_array($model)) {
            return $model;
        }
        $entry = $this->entry($model, $id);
        if (! is_array($entry)) {
            return $entry;
        }

        [$values, $errors] = $this->read($model, $this->valuesOf($entry), $this->posted());
        if ($errors !== []) {
            return $this->form($model, $entry, $values, $errors, lang('Entries.fixErrors'), 422);
        }

        try {
            service('entryStore')->update((int) $id, ['fields' => json_encode($values, self::JSON_FLAGS)], (int) auth()->user()->id);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->form($model, $entry, $values, [], $e->getMessage(), 422);
        }

        // The form is drawn again from what was saved, so the details beside it show the new version.
        $saved = service('entryStore')->findArray((int) $id);

        return $this->form($model, is_array($saved) ? $saved : $entry, $values, [], '', 200, lang('Entries.saved'));
    }

    /* The versions of one entry ------------------------------------------------------------------------------ */

    public function history($modelId, $id)
    {
        $model = $this->model($modelId, 'entries.history');
        if (! is_array($model)) {
            return $model;
        }
        $entry = $this->entry($model, $id);
        if (! is_array($entry)) {
            return $entry;
        }

        $definition = $this->definition($model);
        $versions   = array_reverse(service('entryStore')->versionsOf((int) $id));
        $total      = count($versions);

        $rows = [];
        foreach ($versions as $position => $version) {
            $values = json_decode((string) ($version['fields'] ?? ''), true);
            $rows[] = [
                'id'      => (int) $version['id'],
                'number'  => $total - $position,
                'current' => $position === 0,
                'date'    => $version['date_created'],
                'by'      => $version['created_by'],
                'values'  => is_array($values) && ! array_is_list($values) ? $values : [],
            ];
        }

        $label = $this->labelOf($model, $this->valuesOf($entry), (int) $id);
        $this->data = array_merge($this->data, [
            'title'       => lang('Entries.historyTitle', ['name' => $label]),
            'crumbs'      => [[lang('Admin.entries'), base_url('admin/entries')], [$model['name'], base_url('admin/model/' . (int) $modelId)], [$label, base_url('admin/model/' . (int) $modelId . '/' . (int) $id . '/edit')], [lang('Entries.history'), null]],
            'model'       => $model,
            'entry'       => $entry,
            'label'       => $label,
            'versions'    => $rows,
            'definitions' => $definition,
            'types'       => FieldTypes::core(),
            'editUrl'     => base_url('admin/model/' . (int) $modelId . '/' . (int) $id . '/edit'),
            'baseUrl'     => base_url('admin/model/' . (int) $modelId . '/' . (int) $id),
            'can'         => ['restore' => can('entries.update', (int) $modelId), 'clear' => can('entries.delete', (int) $modelId)],
        ]);

        return view('admin/entry_history', $this->data);
    }

    /**
     * Save the values of an older version as a new version, so the older one stays in the history.
     */
    public function restoreVersion($modelId, $id, $versionId)
    {
        $model = $this->model($modelId, 'entries.update');
        if (! is_array($model)) {
            return $model;
        }
        $entry = $this->entry($model, $id);
        if (! is_array($entry)) {
            return $entry;
        }

        $back = base_url('admin/model/' . (int) $modelId . '/' . (int) $id . '/history');
        $found = null;
        foreach (service('entryStore')->versionsOf((int) $id) as $version) {
            if ((int) $version['id'] === (int) $versionId) {
                $found = $version;
            }
        }
        if ($found === null) {
            return $this->leave($back, lang('Entries.noEntry'));
        }

        try {
            service('entryStore')->update((int) $id, ['fields' => (string) $found['fields']], (int) auth()->user()->id);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->leave($back, $e->getMessage());
        }
        session()->setFlashdata('success', lang('Entries.saved'));

        return $this->isHtmx() ? $this->response->setHeader('HX-Redirect', base_url('admin/model/' . (int) $modelId . '/' . (int) $id . '/edit'))->setStatusCode(204) : redirect()->to(base_url('admin/model/' . (int) $modelId . '/' . (int) $id . '/edit'));
    }

    /**
     * Delete every version of the entry except the current one.
     */
    public function clearHistory($modelId, $id)
    {
        $model = $this->model($modelId, 'entries.delete');
        if (! is_array($model)) {
            return $model;
        }
        $entry = $this->entry($model, $id);
        if (! is_array($entry)) {
            return $entry;
        }

        $back = base_url('admin/model/' . (int) $modelId . '/' . (int) $id . '/history');
        try {
            service('entryStore')->clearHistory((int) $id);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->leave($back, $e->getMessage());
        }
        session()->setFlashdata('success', lang('Entries.historyCleared'));

        return $this->isHtmx() ? $this->response->setHeader('HX-Redirect', $back)->setStatusCode(204) : redirect()->to($back);
    }
    /* The list actions -------------------------------------------------------------------------------------- */

    public function trash($modelId)
    {
        $model = $this->model($modelId, 'entries.delete');
        if (! is_array($model)) {
            return $model;
        }

        $ids = $this->idsOfModel((int) $modelId, false);
        if ($ids === []) {
            return $this->list($model, 'bad', lang('Entries.noneSelected'));
        }

        try {
            service('entryStore')->trash($ids, (int) auth()->user()->id);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->list($model, 'bad', $e->getMessage());
        }

        return $this->list($model, 'ok', lang('Entries.trashed'));
    }

    public function restore($modelId)
    {
        $model = $this->model($modelId, 'entries.delete');
        if (! is_array($model)) {
            return $model;
        }

        $ids = $this->idsOfModel((int) $modelId, true);
        if ($ids === []) {
            return $this->list($model, 'bad', lang('Entries.noneSelected'));
        }

        try {
            service('entryStore')->restore($ids);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->list($model, 'bad', $e->getMessage());
        }

        return $this->list($model, 'ok', lang('Entries.restored'));
    }

    /**
     * Move the entry that is open to the trash, and go back to the list of its model.
     */
    public function trashOne($modelId, $id)
    {
        $model = $this->model($modelId, 'entries.delete');
        if (! is_array($model)) {
            return $model;
        }
        $entry = $this->entry($model, $id);
        if (! is_array($entry)) {
            return $entry;
        }

        $to = base_url('admin/model/' . (int) $modelId);
        try {
            service('entryStore')->trash([(int) $id], (int) auth()->user()->id);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->leave($to, $e->getMessage());
        }
        session()->setFlashdata('success', lang('Entries.trashed'));

        return $this->isHtmx() ? $this->response->setHeader('HX-Redirect', $to)->setStatusCode(204) : redirect()->to($to);
    }
    /**
     * Delete everything in the trash of every model for good. Only a person who may purge can do it.
     */
    public function purge($modelId)
    {
        $model = $this->model($modelId, 'entries.delete');
        if (! is_array($model)) {
            return $model;
        }
        if (! can('entries.purge')) {
            return $this->list($model, 'bad', lang('Entries.notAllowed'));
        }

        try {
            service('entryStore')->emptyTrash();
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->list($model, 'bad', $e->getMessage());
        }

        return $this->list($model, 'ok', lang('Entries.emptied'));
    }

    /* Helpers ----------------------------------------------------------------------------------------------- */

    /**
     * The model as a row, or the redirect to send the person to when there is none or they lack the permission.
     *
     * @return array<string, mixed>|RedirectResponse
     */
    private function model(mixed $modelId, string $permission)
    {
        $model = is_numeric($modelId) ? service('modelStore')->findArray((int) $modelId) : false;
        if (! $model) {
            return $this->leave(base_url('admin/entries'), lang('Entries.noModel'));
        }
        if (! can($permission, (int) $modelId)) {
            return $this->leave(base_url('admin/dashboard'), lang('Auth.notEnoughPrivilege'));
        }

        return $model;
    }

    /**
     * A live entry of this model, or the redirect to send the person to. An entry of another model is answered as if
     * it did not exist, so an address cannot reach an entry through a model the person may open.
     *
     * @param array<string, mixed> $model
     *
     * @return array<string, mixed>|RedirectResponse
     */
    private function entry(array $model, mixed $id)
    {
        $entry = is_numeric($id) ? service('entryStore')->findArray((int) $id) : false;
        if (! $entry || (int) $entry['model_id'] !== (int) $model['id']) {
            $trashed = is_numeric($id) ? (service('entryStore')->trashed([(int) $id]) ?: []) : [];
            $message = $trashed !== [] && (int) ($trashed[0]['model_id'] ?? 0) === (int) $model['id'] ? lang('Entries.trashedNotice') : lang('Entries.noEntry');

            return $this->leave(base_url('admin/model/' . (int) $model['id']), $message);
        }

        return $entry;
    }

    /**
     * Where to go with a message, for a request that cannot be answered as asked.
     */
    private function leave(string $to, string $message): RedirectResponse|ResponseInterface
    {
        if ($this->isHtmx()) {
            return $this->response->setHeader('HX-Redirect', $to)->setStatusCode(204);
        }

        return redirect()->to($to)->with('error', $message);
    }

    /**
     * @param array<string, mixed> $model
     *
     * @return list<array<string, mixed>>
     */
    private function definition(array $model): array
    {
        return (new ModelListPage(FieldTypes::core()))->definition((string) ($model['fields'] ?? ''));
    }

    /**
     * The values of an entry as an array by field id.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function valuesOf(array $entry): array
    {
        $values = json_decode((string) ($entry['fields'] ?? ''), true);

        return is_array($values) && ! array_is_list($values) ? $values : [];
    }

    /**
     * What the form posted under `fields`.
     *
     * @return array<string, mixed>
     */
    private function posted(): array
    {
        $posted = $this->request->getPost('fields');

        return is_array($posted) ? $posted : [];
    }

    /**
     * Read the posted values of a form into the values to store, and the message of each field that cannot be stored.
     *
     * A file field keeps what it holds, because files cannot be changed yet. A password left blank keeps the stored one.
     * A field the definition does not name keeps its value too, so a save never drops what another version of the model
     * had.
     *
     * @param array<string, mixed> $model
     * @param array<string, mixed> $existing The values of the entry as stored, empty for a new one.
     * @param array<string, mixed> $posted
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>} The values, and the error by field id.
     */
    private function read(array $model, array $existing, array $posted): array
    {
        $types  = FieldTypes::core();
        $values = [];
        $errors = [];
        $named  = [];

        foreach ($this->definition($model) as $field) {
            $id        = $field['id'];
            $named[$id] = true;
            $type      = $types->get((string) ($field['type'] ?? ''));
            if ($type === null) {
                continue;
            }

            $kind = (string) $field['type'];
            if ($kind === 'file') {
                if (array_key_exists($id, $existing)) {
                    $values[$id] = $existing[$id];
                }

                continue;
            }
            if ($kind === 'hidden' && ! array_key_exists($id, $posted)) {
                $values[$id] = $existing[$id] ?? ($field['default'] ?? null);

                continue;
            }

            $raw = $posted[$id] ?? null;
            if ($kind === 'text' && ($field['format'] ?? '') === 'password' && ($raw === null || $raw === '') && array_key_exists($id, $existing)) {
                $values[$id] = $existing[$id];

                continue;
            }
            // A list of check boxes posts an empty marker, so a list with nothing ticked still reaches the server.
            if (is_array($raw) && $kind === 'choice') {
                $raw = array_values(array_filter($raw, static fn ($item): bool => $item !== ''));
            }

            $value = $type->normalise($raw, $field);
            $error = $type->validate($value, $field);
            if ($error !== null) {
                $errors[$id] = $error;
            }
            $values[$id] = $value;
        }

        foreach ($existing as $id => $value) {
            if (! isset($named[$id])) {
                $values[$id] = $value;
            }
        }

        return [$values, $errors];
    }

    /**
     * Draw the form, as a whole page or, for htmx, as the form alone.
     *
     * @param array<string, mixed>      $model
     * @param array<string, mixed>|null $entry
     * @param array<string, mixed>      $values
     * @param array<string, string>     $errors
     */
    private function form(array $model, ?array $entry, array $values, array $errors, string $message = '', int $status = 200, string $saved = '')
    {
        $id   = (int) $model['id'];
        $mode = $entry === null ? 'new' : 'edit';
        $data = [
            'model'       => $model,
            'entry'       => $entry,
            'mode'        => $mode,
            'values'      => $values,
            'errors'      => $errors,
            'definitions' => $this->definition($model),
            'types'       => FieldTypes::core(),
            'formUrl'     => $mode === 'new' ? base_url("admin/model/$id") : base_url("admin/model/$id/{$entry['id']}"),
            'listUrl'     => base_url("admin/model/$id"),
            'can'         => ['update' => can($mode === 'new' ? 'entries.create' : 'entries.update', $id), 'delete' => can('entries.delete', $id), 'history' => can('entries.history', $id)],
            'message'     => $message,
        ];

        // A module puts its own button beside the save button, as the paging system puts the editor button.
        $data['toolbar'] = (string) $this->hooks->trigger(hook($mode === 'new' ? 'Backend.view:entries:new' : 'Backend.view:entries:edit'), $mode === 'new' ? [$model] : [$model, $entry]);

        if ($this->isHtmx() && ! $this->request->hasHeader('HX-History-Restore-Request')) {
            $response = $this->response->setStatusCode($status)->setBody(view('admin/partials/entry_form', $data));
            $toast    = $status === 422 ? ['type' => 'bad', 'message' => $message] : ($saved !== '' ? ['type' => 'ok', 'message' => $saved] : null);

            return $toast === null ? $response : $response->setHeader('HX-Trigger', json_encode(['hyper:toast' => $toast], JSON_UNESCAPED_UNICODE));
        }

        $name = $mode === 'new' ? lang('Entries.newTitle', ['model' => $model['name']]) : lang('Entries.editTitle', ['model' => $model['name']]);
        $this->data = array_merge($this->data, $data, [
            'title'  => $name,
            'crumbs' => [[lang('Admin.entries'), base_url('admin/entries')], [$model['name'], $data['listUrl']], [$mode === 'new' ? lang('Entries.create') : $this->labelOf($model, $values, (int) ($entry['id'] ?? 0)), null]],
        ]);

        return $this->response->setStatusCode($status)->setBody(view('admin/entry_form', $this->data));
    }

    /**
     * The name of an entry for the trail of the page.
     *
     * @param array<string, mixed> $model
     * @param array<string, mixed> $values
     */
    private function labelOf(array $model, array $values, int $id): string
    {
        $field = $model['label_field'] ?? null;

        return is_string($field) && is_scalar($values[$field] ?? null) && (string) $values[$field] !== '' ? (string) $values[$field] : '#' . $id;
    }

    /**
     * The ids that were posted, kept to entries of this model in the state the action needs.
     *
     * @return list<int>
     */
    private function idsOfModel(int $modelId, bool $inTrash): array
    {
        $posted = $this->request->getPost('ids');
        $ids    = array_values(array_unique(array_filter(array_map('intval', is_array($posted) ? $posted : []), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $found = [];
        if ($inTrash) {
            foreach (service('entryStore')->trashed($ids) ?: [] as $row) {
                $found[(int) $row['id']] = (int) $row['model_id'];
            }
        } else {
            foreach ($ids as $id) {
                $found[$id] = service('entryStore')->modelIdOf($id) ?? 0;
            }
        }

        return array_values(array_filter($ids, static fn (int $id): bool => ($found[$id] ?? 0) === $modelId));
    }

    /**
     * The list of the model as it looks after an action, with a message, under the request the person had.
     *
     * @param array<string, mixed> $model
     */
    private function list(array $model, string $type, string $message)
    {
        $per  = (int) (service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 25);
        $data = (new ModelListPage(FieldTypes::core()))->viewData($model, $this->request->getPost() ?: [], $per);

        if ($this->isHtmx()) {
            return $this->response
                ->setHeader('HX-Trigger', json_encode(['hyper:toast' => ['type' => $type, 'message' => $message]], JSON_UNESCAPED_UNICODE))
                ->setBody(view('admin/partials/entry_results', $data));
        }

        return redirect()->to($data['urls']['list'])->with($type === 'ok' ? 'success' : 'error', $message);
    }

    private function isHtmx(): bool
    {
        return $this->request->hasHeader('HX-Request');
    }
}
