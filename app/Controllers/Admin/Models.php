<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Data\Abilities;
use App\Data\IndexingFailed;
use App\Data\ModelGrants;
use App\Data\Permissions;
use App\Fields\DefinitionCheck;
use App\Fields\FieldCatalog;
use App\Fields\FieldTypes;
use App\Libraries\ModelsPage;
use InvalidArgumentException;
use RuntimeException;

class Models extends AdminController
{
    public function index()
    {
        $data = (new ModelsPage())->build($this->request->getGet(), $this->perPage());

        // A swap of the list gets the list alone. The browser's own restore of a page after Back needs the whole page.
        if (Model::wantsTheList($this->request, 'models')) {
            return view('admin/partials/model_results', $data);
        }

        $this->data = array_merge($this->data, $data, [
            'title'  => lang('Models.models'),
            'crumbs' => [[lang('Models.models'), null]],
        ]);

        return view('admin/models', $this->data);
    }

    public function new()
    {
        // A new model starts with one field that names its entries, so it is usable as soon as it is saved.
        $starter = [['id' => 'title', 'label' => lang('Models.starterTitle'), 'type' => 'text', 'required' => true]];

        return $this->builder([], $starter, 'title', [], []);
    }

    public function edit($id)
    {
        $model = is_numeric($id) ? service('modelStore')->findArray((int) $id) : false;
        if (! $model) {
            return redirect()->to(base_url('admin/models'))->with('error', lang('Models.notFound'));
        }

        $fields = json_decode((string) $model['fields'], true);

        return $this->builder($model, is_array($fields) ? $fields : [], (string) ($model['label_field'] ?? ''), [], []);
    }

    /**
     * Create a model from the builder.
     */
    public function create()
    {
        [$data, $fields, $errors] = $this->submitted();
        if ($errors !== []) {
            return $this->builder([], $fields, (string) ($data['label_field'] ?? ''), $errors, $this->typed($data), 422);
        }

        try {
            $id = service('modelStore')->create($data, (int) auth()->user()->id);
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->builder([], $fields, (string) ($data['label_field'] ?? ''), ['general' => [$e->getMessage()]], $this->typed($data), 422);
        }

        return redirect()->to(base_url('admin/models/' . (int) $id . '/edit'))->with('success', lang('Models.created', ['name' => $data['name']]));
    }

    public function update($id)
    {
        $model = is_numeric($id) ? service('modelStore')->findArray((int) $id) : false;
        if (! $model) {
            return redirect()->to(base_url('admin/models'))->with('error', lang('Models.notFound'));
        }

        [$data, $fields, $errors] = $this->submitted((int) $id);
        if ($errors !== []) {
            return $this->builder($model, $fields, (string) ($data['label_field'] ?? ''), $errors, $this->typed($data), 422);
        }

        try {
            service('modelStore')->update((int) $id, $data, (int) auth()->user()->id);
        } catch (IndexingFailed $e) {
            // The model is saved. Only the start of the indexing failed, and saving again tries it again.
            return redirect()->to(base_url('admin/models/' . (int) $id . '/edit'))->with('warning', $e->getMessage());
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->builder($model, $fields, (string) ($data['label_field'] ?? ''), ['general' => [$e->getMessage()]], $this->typed($data), 422);
        }

        return redirect()->to(base_url('admin/models/' . (int) $id . '/edit'))->with('success', lang('Models.saved', ['name' => $data['name']]));
    }

    /**
     * Check a definition without saving it, for the JSON editor. The answer lists each problem with the id of its field.
     */
    public function check()
    {
        $decoded = json_decode((string) $this->request->getPost('fields'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->response->setJSON(['ok' => false, 'errors' => [['field' => null, 'message' => lang('Models.jsonInvalid', ['message' => json_last_error_msg()])]]]);
        }

        $check  = new DefinitionCheck(FieldTypes::core(), $this->modelNames());
        $result = $check->check($decoded);
        $label  = $check->checkLabelField((string) $this->request->getPost('label_field'), $result['fields']);
        if ($label !== null) {
            $result['errors'][] = ['field' => null, 'message' => $label];
        }

        return $this->response->setJSON(['ok' => $result['errors'] === [], 'errors' => $result['errors'], 'fields' => $result['fields']]);
    }

    /**
     * What the builder posted, read and checked.
     *
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: array<string, mixed>} The data for the store,
     *         the fields as far as they could be read, and the errors by place (`general`, `name`, `group`, `fields` by id).
     */
    private function submitted(?int $modelId = null): array
    {
        $post   = $this->request->getPost();
        $errors = [];

        $rules = [
            'name'  => 'required|min_length[3]|max_length[255]',
            'group' => 'permit_empty|min_length[3]|alpha_space',
            'icon'  => 'permit_empty|max_length[255]',
        ];
        $input = [
            'name'  => trim((string) ($post['name'] ?? '')),
            'group' => trim((string) ($post['group'] ?? '')),
            'icon'  => trim((string) ($post['icon'] ?? '')),
        ];
        if (! $this->validateData($input, $rules)) {
            foreach ($this->validator->getErrors() as $key => $message) {
                $errors[$key] = [$message];
            }
        }

        $decoded = json_decode((string) ($post['fields'] ?? ''), true);
        $fields  = [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors['general'][] = lang('Models.jsonInvalid', ['message' => json_last_error_msg()]);
        } else {
            $check  = new DefinitionCheck(FieldTypes::core(), $this->modelNames());
            $result = $check->check($decoded);
            $fields = $result['fields'];
            foreach ($result['errors'] as $error) {
                if ($error['field'] === null) {
                    $errors['general'][] = $error['message'];
                } else {
                    $errors['fields'][$error['field']][] = $error['message'];
                }
            }

            $note = $check->checkLabelField((string) ($post['label_field'] ?? ''), $fields);
            if ($note !== null) {
                $errors['general'][] = $note;
            }
        }

        $data = [
            'name'        => $input['name'],
            'group'       => $input['group'] === '' ? null : $input['group'],
            'icon'        => $input['icon'] === '' ? null : $input['icon'],
            'access'      => $this->accessFrom($post['access'] ?? null, $modelId),
            'fields'      => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'label_field' => ($post['label_field'] ?? '') === '' ? null : (string) $post['label_field'],
        ];

        return [$data, $fields, $errors];
    }

    /**
     * The access grid after the form, for every role but the superadmin, which needs no row.
     *
     * A person hands out only what they hold themselves, as on the roles screen. A role they may not handle keeps what it
     * has, and an action they lack on the model stays as it was for a role they may handle, so saving the model can never
     * give anyone more than the person who saves it has. Anything other than reading also lets the role read, when the
     * person holds reading or the role already has it.
     *
     * @param int|null $modelId The model being saved, or null for a new one.
     *
     * @return array<string, list<string>>
     */
    private function accessFrom(mixed $posted, ?int $modelId): array
    {
        $permissions = service('permissions');
        $actor       = auth()->user();
        $existing    = $modelId !== null ? service('grants')->forModel($modelId) : [];
        $posted      = is_array($posted) ? $posted : [];

        $mine = array_values(array_filter(Abilities::MODEL_ACTIONS, fn (string $action): bool => $this->holds($action, $modelId)));

        $access = [];
        foreach (service('roles')->names() as $role) {
            if ($role === Permissions::SUPERADMIN) {
                continue;
            }

            $before = array_values(array_intersect(Abilities::MODEL_ACTIONS, $existing[$role] ?? []));

            // A role the person may not handle keeps exactly what it has.
            if (! $permissions->mayAssignRole($actor, $role)) {
                if ($before !== []) {
                    $access[$role] = $before;
                }

                continue;
            }

            $asked   = is_array($posted[$role] ?? null) ? array_map('strval', $posted[$role]) : [];
            $actions = array_values(array_intersect(Abilities::MODEL_ACTIONS, array_unique(array_merge(array_diff($before, $mine), array_intersect($asked, $mine)))));
            if ($actions !== [] && ! in_array('read', $actions, true) && (in_array('read', $mine, true) || in_array('read', $before, true))) {
                array_unshift($actions, 'read');
            }
            if ($actions !== []) {
                $access[$role] = $actions;
            }
        }

        return $access;
    }

    /**
     * Whether the signed-in person holds an action on a model. A model that does not exist yet has no rights to hold, and
     * what a person holds on other models says nothing about it, so only a superadmin, who has everything, may set the
     * access of a new model. Anyone else makes it with no access for others, and a superadmin gives it later.
     */
    private function holds(string $action, ?int $modelId): bool
    {
        if ($modelId === null) {
            return service('permissions')->mayAssignRole(auth()->user(), Permissions::SUPERADMIN);
        }

        try {
            return can('entries.' . $action, $modelId);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * What the person typed, as the page needs it to draw the form again after an error.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function typed(array $data): array
    {
        return [
            'name'        => (string) ($data['name'] ?? ''),
            'group'       => (string) ($data['group'] ?? ''),
            'icon'        => (string) ($data['icon'] ?? ''),
            'access'      => is_array($data['access'] ?? null) ? $data['access'] : [],
        ];
    }

    /**
     * @return array<int, string> The live models by id, which a relation can point at.
     */
    private function modelNames(): array
    {
        $names = [];
        foreach (service('modelStore')->allArray() as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }

        return $names;
    }

    /**
     * The icons of the sprite that suit a model, in the order the picker shows them.
     */
    public const ICONS = ['models', 'doc', 'entries', 'pages', 'media', 'folder', 'table', 'inbox', 'mail', 'users', 'user', 'key', 'bolt', 'search', 'eye', 'settings'];

    /**
     * The builder page.
     *
     * @param array<string, mixed>       $model  The saved model, or empty for a new one.
     * @param list<array<string, mixed>> $fields The fields to show.
     * @param array<string, mixed>       $errors By place.
     * @param array<string, mixed>       $typed  Values typed in a form that came back with errors.
     */
    private function builder(array $model, array $fields, string $labelField, array $errors, array $typed, int $status = 200)
    {
        $id    = (int) ($model['id'] ?? 0);
        $roles = array_values(array_filter(service('roles')->all(), static fn (array $role): bool => $role['name'] !== Permissions::SUPERADMIN));

        // A new model starts with what a model with no groups meant: every role but user may read it, and the staff write.
        $access = $typed['access'] ?? null;
        if ($access === null) {
            $access = $id > 0 ? service('grants')->forModel($id) : [];
            if ($id === 0) {
                foreach (ModelGrants::fromGroups([], array_column($roles, 'name')) as $row) {
                    $access[$row['role']][] = $row['action'];
                }
            }
        }

        $names  = $this->modelNames();
        $values = [
            'name'   => $typed['name'] ?? (string) ($model['name'] ?? ''),
            'group'  => $typed['group'] ?? (string) ($model['group'] ?? ''),
            'icon'   => $typed['icon'] ?? (string) ($model['icon'] ?? ''),
            'access' => $access,
        ];

        $models = [];
        foreach ($names as $modelId => $name) {
            $models[] = ['id' => $modelId, 'name' => $name];
        }

        $storedFields = $id > 0 ? json_decode((string) ($model['fields'] ?? '[]'), true) : [];
        $savedIds     = [];
        foreach (is_array($storedFields) ? $storedFields : [] as $field) {
            if (is_array($field) && is_string($field['id'] ?? null)) {
                $savedIds[] = $field['id'];
            }
        }

        $payload = [
            'catalog'    => (new FieldCatalog(FieldTypes::core()))->forBuilder($models),
            'fields'     => $fields,
            'labelField' => $labelField,
            'isNew'      => $id === 0,
            'saved'      => $savedIds,
            'indexing'   => $id > 0 ? service('modelStore')->filterableState($id) : [],
            'errors'     => $errors['fields'] ?? [],
            'urls'       => ['check' => base_url('admin/models/check')],
            'text'       => $this->builderText(),
            'icons'      => self::ICONS,
        ];

        $title = $id > 0 ? (string) $model['name'] : lang('Models.newModel');

        $this->data = array_merge($this->data, [
            'title'   => $title,
            'crumbs'  => [[lang('Models.models'), base_url('admin/models')], [$title, null]],
            'model'   => $model,
            'id'      => $id,
            'values'  => $values,
            'roles'   => $roles,
            'payload' => $payload,
            'errors'  => $errors,
            'entries' => $id > 0 ? (service('entryStore')->countsByModel()[$id] ?? 0) : 0,
            'formUrl' => $id > 0 ? base_url('admin/models/' . $id) : base_url('admin/models'),
            'urls'    => ['history' => $id > 0 ? base_url('admin/model-data/' . $id) : '', 'entries' => $id > 0 ? base_url('admin/model/' . $id) : '', 'trash' => $id > 0 ? base_url('admin/models/' . $id . '/delete') : ''],
        ]);

        return $this->response->setStatusCode($status)->setBody(view('admin/model_builder', $this->data));
    }

    /**
     * The words the builder's script writes into the page. It cannot ask for a translation, so they travel with the page.
     *
     * @return array<string, string>
     */
    private function builderText(): array
    {
        $keys = ['addField', 'chooseType', 'removeField', 'moveUp', 'moveDown', 'dragHandle', 'fieldId', 'fieldIdLocked', 'fieldLabel', 'fieldType', 'fieldHelp', 'fieldDefault', 'required', 'showInList', 'filterable', 'filterableNever', 'filterableSingle', 'stateIndexed', 'stateIndexing', 'stateOnSave', 'nameEntriesBy', 'nameEntriesHelp', 'nameNone', 'nameChip', 'addChoice', 'choiceValue', 'choiceLabel', 'removeChoice', 'noFields', 'noFieldsText', 'selectField', 'unsaved', 'tabFields', 'tabJson', 'tabDetails', 'jsonLoading', 'jsonFailed', 'jsonCheck', 'jsonOk', 'jsonInvalid', 'storedDefinition', 'pickModel', 'noModels', 'optional', 'untitled', 'movedTo', 'removed', 'added', 'typeChanged', 'defaultChecked', 'optionMany', 'filterableHelp', 'indexNote', 'jsonNotList', 'jsonFailedCheck'];
        $text = [];
        foreach ($keys as $key) {
            $text[$key] = lang('Models.b' . ucfirst($key));
        }

        return $text;
    }

    public function delete($id = null)
    {
        $ids = $this->postedIds($id);
        if ($ids === []) {
            return $this->list('bad', lang('Models.noneSelected'));
        }

        try {
            service('modelStore')->trash($ids, (int) auth()->user()->id);
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->list('bad', $e->getMessage());
        }

        return $this->list('ok', lang('Models.trashed'));
    }

    public function purgeDeleted()
    {
        if ((int) service('modelStore')->countTrashed() === 0) {
            return $this->list('bad', lang('Models.trashIsEmpty'));
        }

        try {
            service('modelStore')->emptyTrash();
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->list('bad', $e->getMessage());
        }

        return $this->list('ok', lang('Models.emptied'));
    }

    public function restore($id = null)
    {
        $ids = $this->postedIds($id);
        if ($ids === []) {
            return $this->list('bad', lang('Models.noneSelected'));
        }

        try {
            service('modelStore')->restore($ids);
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->list('bad', $e->getMessage());
        }

        return $this->list('ok', lang('Models.restored'));
    }

    /**
     * The ids that were posted, or the one in the address, as whole numbers.
     *
     * @return list<int>
     */
    private function postedIds(mixed $id): array
    {
        $posted = $this->request->getPost('ids');
        $ids    = is_array($posted) ? $posted : ($id !== null && $id !== '' ? [$id] : []);

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $n): bool => $n > 0)));
    }

    /**
     * The list as it looks after an action, with a message, under the request the person had.
     */
    private function list(string $type, string $message)
    {
        $data = (new ModelsPage())->build($this->request->getPost() ?: [], $this->perPage());

        if ($this->request->hasHeader('HX-Request')) {
            return $this->response
                ->setHeader('HX-Trigger', json_encode(['hyper:toast' => ['type' => $type, 'message' => $message]], JSON_UNESCAPED_UNICODE))
                ->setBody(view('admin/partials/model_results', $data));
        }

        return redirect()->to($data['urls']['list'])->with($type === 'ok' ? 'success' : 'error', $message);
    }

    private function perPage(): int
    {
        return (int) (service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 25);
    }
}
