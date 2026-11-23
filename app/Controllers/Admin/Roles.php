<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Data\Abilities;
use App\Data\Permissions;
use InvalidArgumentException;

/**
 * The roles of the admin: the list, and one role with what it may do. A person who may manage roles can only hand out
 * what they have themselves, so editing a role is never a way to reach more than their own roles allow.
 */
class Roles extends AdminController
{
    public function index()
    {
        $roles = service('roles')->all();
        $rows  = [];
        foreach ($roles as $role) {
            $edit   = base_url('admin/roles/' . $role['name'] . '/edit');
            $rows[] = [
                'id'      => $role['name'],
                'label'   => $role['label'],
                'cells'   => [
                    'label'     => '<a href="' . attr($edit) . '">' . esc($role['label']) . '</a>' . ($role['locked'] ? ' <span class="chip">' . esc(lang('Roles.locked')) . '</span>' : ''),
                    'name'      => '<code>' . esc($role['name']) . '</code>',
                    'text'      => esc($role['description']),
                    'people'    => (string) $role['people'],
                    'abilities' => $role['locked'] ? esc(lang('Roles.everything')) : (string) $role['abilities'],
                ],
                'actions' => ui('button', ['label' => lang('Roles.edit'), 'href' => $edit, 'variant' => 'quiet', 'size' => 'small']),
            ];
        }

        $this->data = array_merge($this->data, [
            'title'   => lang('Roles.title'),
            'crumbs'  => [[lang('Roles.title'), null]],
            'count'   => count($roles),
            'columns' => [
                ['key' => 'label', 'label' => lang('Roles.label'), 'class' => 'label-cell'],
                ['key' => 'name', 'label' => lang('Roles.name')],
                ['key' => 'text', 'label' => lang('Roles.description')],
                ['key' => 'people', 'label' => lang('Roles.people'), 'class' => 'num'],
                ['key' => 'abilities', 'label' => lang('Roles.abilitiesCount'), 'class' => 'num'],
            ],
            'rows'    => $rows,
        ]);

        return view('admin/roles', $this->data);
    }

    public function new()
    {
        return $this->form(null, ['label' => '', 'name' => '', 'description' => ''], [], [], '');
    }

    public function create()
    {
        $post = $this->request->getPost();
        $name = trim((string) ($post['name'] ?? ''));
        $copy = (string) ($post['copy'] ?? '');

        try {
            // A role can only be started from one the person may hand out.
            if ($copy !== '' && ! service('permissions')->mayAssignRole(auth()->user(), $copy)) {
                throw new InvalidArgumentException(lang('Roles.notAllowedToCopy'));
            }
            service('roles')->create($name, (string) ($post['label'] ?? ''), (string) ($post['description'] ?? ''), $copy);
            $this->save($name, $post, true);
        } catch (InvalidArgumentException $e) {
            return $this->form(null, ['label' => (string) ($post['label'] ?? ''), 'name' => $name, 'description' => (string) ($post['description'] ?? '')], [], [], $e->getMessage(), 422);
        }

        return redirect()->to(base_url('admin/roles/' . $name . '/edit'))->with('success', lang('Roles.created', ['name' => (string) ($post['label'] ?? $name)]));
    }

    public function edit($name)
    {
        $role = service('roles')->find((string) $name);
        if ($role === null) {
            return redirect()->to(base_url('admin/roles'))->with('error', lang('Roles.notFound'));
        }

        return $this->form($role, $role, service('roles')->abilitiesOf($role['name']), service('roles')->modelActionsOf($role['name']), '');
    }

    public function update($name)
    {
        $role = service('roles')->find((string) $name);
        if ($role === null) {
            return redirect()->to(base_url('admin/roles'))->with('error', lang('Roles.notFound'));
        }

        $post = $this->request->getPost();

        try {
            if (! service('permissions')->mayAssignRole(auth()->user(), $role['name'])) {
                throw new InvalidArgumentException(lang('Roles.notAllowedToChange'));
            }
            service('roles')->update($role['name'], (string) ($post['label'] ?? ''), (string) ($post['description'] ?? ''));
            $this->save($role['name'], $post, false);
        } catch (InvalidArgumentException $e) {
            return $this->form($role, ['label' => (string) ($post['label'] ?? ''), 'name' => $role['name'], 'description' => (string) ($post['description'] ?? '')] + $role, service('roles')->abilitiesOf($role['name']), service('roles')->modelActionsOf($role['name']), $e->getMessage(), 422);
        }

        return redirect()->to(base_url('admin/roles/' . $role['name'] . '/edit'))->with('success', lang('Roles.saved', ['name' => (string) ($post['label'] ?? $role['name'])]));
    }

    public function delete($name)
    {
        $role = service('roles')->find((string) $name);

        try {
            if ($role !== null && ! service('permissions')->mayAssignRole(auth()->user(), $role['name'])) {
                throw new InvalidArgumentException(lang('Roles.notAllowedToChange'));
            }
            service('roles')->delete((string) $name);
        } catch (InvalidArgumentException $e) {
            return redirect()->to(base_url('admin/roles'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('admin/roles'))->with('success', lang('Roles.deleted', ['name' => $role['label'] ?? (string) $name]));
    }

    /**
     * Write what a role may do from the posted form. A person changes only what they have themselves: an ability or an
     * action they lack is left as it was, and the form shows it as locked.
     *
     * @param array<string, mixed> $post
     */
    private function save(string $name, array $post, bool $isNew): void
    {
        if ($name === Permissions::SUPERADMIN) {
            return;
        }

        $roles = service('roles');
        $known = array_keys(service('abilities')->all());

        // Global abilities: the ones the person has are set from the form, the others stay.
        $mine    = array_values(array_filter($known, static fn (string $ability): bool => self::has($ability)));
        $posted  = is_array($post['abilities'] ?? null) ? array_map('strval', $post['abilities']) : [];
        $current = $roles->abilitiesOf($name);
        $roles->setAbilities($name, array_merge(array_diff($current, $mine), array_intersect($posted, $mine)), $known);

        // Actions on models, the same way.
        $modelIds = service('modelStore')->allIds();
        $existing = $roles->modelActionsOf($name);
        $byModel  = [];
        foreach ($modelIds as $modelId) {
            $kept = array_values(array_filter($existing[$modelId] ?? [], static fn (string $action): bool => ! self::has('entries.' . $action, $modelId)));
            $form = is_array($post['models'][$modelId] ?? null) ? array_map('strval', $post['models'][$modelId]) : [];
            $mineActions = array_values(array_filter(Abilities::MODEL_ACTIONS, static fn (string $action): bool => self::has('entries.' . $action, $modelId)));
            $byModel[$modelId] = array_merge($kept, array_intersect($form, $mineActions));
        }
        $roles->setModelActions($name, $byModel, $modelIds);
    }

    private static function has(string $ability, ?int $modelId = null): bool
    {
        try {
            return can($ability, $modelId);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @param array<string, mixed>|null  $role      The saved role, or null for a new one.
     * @param array<string, mixed>       $values    What the form shows.
     * @param list<string>               $abilities The global abilities the role has.
     * @param array<int, list<string>>   $actions   What the role may do on each model.
     */
    private function form(?array $role, array $values, array $abilities, array $actions, string $error, int $status = 200)
    {
        $abilityRows = [];
        foreach (service('abilities')->all() as $ability) {
            $abilityRows[] = $ability + ['has' => in_array($ability['name'], $abilities, true), 'mine' => self::has($ability['name'])];
        }

        $models = [];
        foreach (service('modelStore')->allArray() as $model) {
            $id       = (int) $model['id'];
            $models[] = ['id' => $id, 'name' => (string) $model['name'], 'group' => (string) ($model['group'] ?? ''), 'actions' => $actions[$id] ?? [], 'mine' => array_values(array_filter(Abilities::MODEL_ACTIONS, static fn (string $a): bool => self::has('entries.' . $a, $id)))];
        }

        $others = array_values(array_filter(service('roles')->all(), static fn (array $r): bool => $r['name'] !== Permissions::SUPERADMIN && service('permissions')->mayAssignRole(auth()->user(), $r['name'])));

        $title = $role === null ? lang('Roles.newRole') : (string) $role['label'];
        $this->data = array_merge($this->data, [
            'title'     => $title,
            'crumbs'    => [[lang('Roles.title'), base_url('admin/roles')], [$title, null]],
            'role'      => $role,
            'values'    => $values,
            'abilities' => $abilityRows,
            'models'    => $models,
            'others'    => $others,
            'error'     => $error,
            'locked'    => $role !== null && $role['locked'],
            'formUrl'   => $role === null ? base_url('admin/roles') : base_url('admin/roles/' . $role['name']),
            'deleteUrl' => $role === null ? '' : base_url('admin/roles/' . $role['name'] . '/delete'),
        ]);

        return $this->response->setStatusCode($status)->setBody(view('admin/role_form', $this->data));
    }
}
