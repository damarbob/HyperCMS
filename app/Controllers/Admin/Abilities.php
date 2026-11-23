<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use InvalidArgumentException;

/**
 * The abilities of the admin as a catalog: what each one is, where it comes from and which roles have it. An admin can
 * add an ability by name, and take away one that was added by hand. Giving an ability to a role is done on the role.
 */
class Abilities extends AdminController
{
    public function index()
    {
        return $this->page('', ['name' => '', 'label' => '', 'description' => ''], 200);
    }

    public function create()
    {
        $post = $this->request->getPost();
        $name = trim((string) ($post['name'] ?? ''));

        try {
            service('abilities')->createCustom($name, (string) ($post['label'] ?? ''), (string) ($post['description'] ?? ''));
        } catch (InvalidArgumentException $e) {
            return $this->page($e->getMessage(), ['name' => $name, 'label' => (string) ($post['label'] ?? ''), 'description' => (string) ($post['description'] ?? '')], 422);
        }

        return redirect()->to(base_url('admin/abilities'))->with('success', lang('Roles.abCreated', ['name' => $name]));
    }

    public function delete($name)
    {
        try {
            // Deleting an ability takes it from every role that has it, so it is allowed only when the person may change
            // each of those roles. Otherwise a role manager could strip power from a role above their own.
            foreach (service('abilities')->holders()[(string) $name] ?? [] as $role) {
                if (! service('permissions')->mayAssignRole(auth()->user(), $role)) {
                    throw new InvalidArgumentException(lang('Roles.abilityHeldAbove'));
                }
            }
            service('abilities')->deleteCustom((string) $name);
            service('grants')->forget();
        } catch (InvalidArgumentException $e) {
            return redirect()->to(base_url('admin/abilities'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('admin/abilities'))->with('success', lang('Roles.abDeleted', ['name' => (string) $name]));
    }

    /**
     * @param array<string, string> $values What the form to add an ability shows.
     */
    private function page(string $error, array $values, int $status)
    {
        $holders = service('abilities')->holders();
        $labels  = array_column(service('roles')->all(), 'label', 'name');

        $rows = [];
        foreach (service('abilities')->all() as $ability) {
            $roles = array_map(static fn (string $role): string => '<span class="chip">' . esc($labels[$role] ?? $role) . '</span>', $holders[$ability['name']] ?? []);
            $owner = $ability['owner'] === 'core' ? lang('Roles.ownerCore') : ($ability['owner'] === 'custom' ? lang('Roles.ownerCustom') : $ability['owner']);

            $rows[] = [
                'id'      => $ability['name'],
                'label'   => $ability['label'],
                'cells'   => [
                    'label'  => '<strong>' . esc($ability['label']) . '</strong><br><code>' . esc($ability['name']) . '</code>',
                    'text'   => esc($ability['description']),
                    'owner'  => esc($owner),
                    'roles'  => '<div class="chips"><span class="chip tint">' . esc(lang('Roles.abAllRoles')) . '</span>' . implode('', $roles) . '</div>',
                ],
                'actions' => $ability['custom']
                    ? '<form method="post" action="' . attr(base_url('admin/abilities/' . $ability['name'] . '/delete')) . '" onsubmit="return confirm(' . attr(json_encode(lang('Roles.abConfirmDelete'), JSON_UNESCAPED_UNICODE)) . ')">' . csrf_field() . ui('button', ['label' => lang('Roles.abDelete'), 'type' => 'submit', 'variant' => 'quiet', 'size' => 'small']) . '</form>'
                    : '',
            ];
        }

        $this->data = array_merge($this->data, [
            'title'   => lang('Roles.abTitle'),
            'crumbs'  => [[lang('Roles.title'), base_url('admin/roles')], [lang('Roles.abTitle'), null]],
            'columns' => [
                ['key' => 'label', 'label' => lang('Roles.abLabel'), 'class' => 'label-cell'],
                ['key' => 'text', 'label' => lang('Roles.abDescription')],
                ['key' => 'owner', 'label' => lang('Roles.abSource')],
                ['key' => 'roles', 'label' => lang('Roles.abRoles')],
            ],
            'rows'    => $rows,
            'error'   => $error,
            'values'  => $values,
        ]);

        return $this->response->setStatusCode($status)->setBody(view('admin/abilities', $this->data));
    }
}
