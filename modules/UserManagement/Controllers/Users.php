<?php

namespace UserManagement\Controllers;

use App\Fields\FieldTypes;
use App\Modules\ModuleController;
use CodeIgniter\Shield\Entities\User;
use UserManagement\Libraries\UserList;

/**
 * The people who can sign in. Every change goes through two rules of core: a person may handle only a role whose power
 * they have themselves, and the last superadmin can neither lose the role nor be removed. Nobody can delete or ban their
 * own account here.
 */
class Users extends ModuleController
{
    public function index()
    {
        $data = $this->listData($this->request->getGet());

        // A swap of the list gets the list alone. The browser's own restore of a page after Back needs the whole page.
        if ($this->request->hasHeader('HX-Request') && ! $this->request->hasHeader('HX-History-Restore-Request') && $this->request->getHeaderLine('HX-Target') === 'users') {
            return view('UserManagement\Views\partials\results', $data);
        }

        return $this->page('UserManagement\Views\users', $data + [
            'title'  => lang('UserManagement.moduleName'),
            'crumbs' => [[lang('UserManagement.moduleName'), null]],
        ]);
    }

    public function new()
    {
        return $this->form(null, ['username' => '', 'email' => '', 'roles' => []], []);
    }

    public function create()
    {
        $post   = $this->request->getPost();
        $values = $this->valuesFrom($post);
        $errors = $this->check($values, null, (string) ($post['password'] ?? ''));

        $roles = $this->rolesAfter([], $values['roles']);
        $user  = null;
        if ($errors === []) {
            try {
                $users = auth()->getProvider();
                $users->save(new User(['username' => $values['username'], 'email' => $values['email'], 'password' => (string) $post['password']]));
                $user = $users->findById($users->getInsertID());
                $user->syncGroups(...$roles);
            } catch (\Throwable $e) {
                $errors['general'] = $e->getMessage();
            }
        }
        if ($errors !== [] || $user === null) {
            return $this->form(null, $values, $errors, 422);
        }

        return $this->leave(base_url('admin/users/' . (int) $user->id . '/edit'), lang('UserManagement.created', ['name' => $values['username']]), 'ok');
    }

    public function edit($id)
    {
        $user = $this->find($id);
        if ($user === null) {
            return $this->leave(base_url('admin/users'), lang('UserManagement.userNotFound'));
        }

        return $this->form($user, ['username' => (string) $user->username, 'email' => (string) $user->email, 'roles' => array_map('strtolower', $user->getGroups() ?? [])], []);
    }

    public function update($id)
    {
        $user = $this->find($id);
        if ($user === null) {
            return $this->leave(base_url('admin/users'), lang('UserManagement.userNotFound'));
        }

        if (! service('permissions')->mayManageUser(auth()->user(), $user)) {
            return $this->leave(base_url('admin/users/' . (int) $user->id . '/edit'), lang('UserManagement.notAllowedToHandle'));
        }

        $post    = $this->request->getPost();
        $values  = $this->valuesFrom($post);
        $current = array_map('strtolower', $user->getGroups() ?? []);
        $errors  = $this->check($values, $user, (string) ($post['password'] ?? ''));

        // The roles the person may not handle stay as they are, and the others follow the form.
        $final = $this->rolesAfter($current, $values['roles']);
        if (in_array('superadmin', $current, true) && ! in_array('superadmin', $final, true) && $this->superadmins() <= 1) {
            $errors['general'] = lang('UserManagement.lastSuperadmin');
        }

        if ($errors === []) {
            try {
                $user->fill(['username' => $values['username'], 'email' => $values['email']] + (trim((string) ($post['password'] ?? '')) !== '' ? ['password' => (string) $post['password']] : []));
                auth()->getProvider()->save($user);
                $user->syncGroups(...$final);
            } catch (\Throwable $e) {
                $errors['general'] = $e->getMessage();
            }
        }
        if ($errors !== []) {
            return $this->form($user, $values, $errors, 422);
        }

        return $this->leave(base_url('admin/users/' . (int) $user->id . '/edit'), lang('UserManagement.saved', ['name' => $values['username']]), 'ok');
    }

    public function ban($id)
    {
        $user = $this->find($id);
        $back = base_url('admin/users/' . (int) $id . '/edit');
        if ($user === null) {
            return $this->leave(base_url('admin/users'), lang('UserManagement.userNotFound'));
        }
        if (! service('permissions')->mayManageUser(auth()->user(), $user)) {
            return $this->leave($back, lang('UserManagement.notAllowedToHandle'));
        }
        if ((int) $user->id === (int) auth()->id()) {
            return $this->leave($back, lang('UserManagement.cannotBanYourself'));
        }
        if ($user->inGroup('superadmin') && $this->superadmins() <= 1) {
            return $this->leave($back, lang('UserManagement.lastSuperadmin'));
        }

        $message = trim((string) $this->request->getPost('message'));
        $user->ban($message === '' ? null : mb_substr($message, 0, 255));
        auth()->getProvider()->save($user);

        return $this->leave($back, lang('UserManagement.banned', ['name' => (string) $user->username]), 'ok');
    }

    public function unban($id)
    {
        $user = $this->find($id);
        $back = base_url('admin/users/' . (int) $id . '/edit');
        if ($user === null) {
            return $this->leave(base_url('admin/users'), lang('UserManagement.userNotFound'));
        }
        if (! service('permissions')->mayManageUser(auth()->user(), $user)) {
            return $this->leave($back, lang('UserManagement.notAllowedToHandle'));
        }

        $user->unBan();
        auth()->getProvider()->save($user);

        return $this->leave($back, lang('UserManagement.unbanned', ['name' => (string) $user->username]), 'ok');
    }

    public function delete($id)
    {
        $user = $this->find($id);
        $back = base_url('admin/users/' . (int) $id . '/edit');
        if ($user === null) {
            return $this->leave(base_url('admin/users'), lang('UserManagement.userNotFound'));
        }
        if (! service('permissions')->mayManageUser(auth()->user(), $user)) {
            return $this->leave($back, lang('UserManagement.notAllowedToHandle'));
        }
        if ((int) $user->id === (int) auth()->id()) {
            return $this->leave($back, lang('UserManagement.cannotDeleteYourself'));
        }
        if ($user->inGroup('superadmin') && $this->superadmins() <= 1) {
            return $this->leave($back, lang('UserManagement.lastSuperadmin'));
        }

        auth()->getProvider()->delete($user->id, true);

        return $this->leave(base_url('admin/users'), lang('UserManagement.deleted', ['name' => (string) $user->username]), 'ok');
    }

    /* Helpers ----------------------------------------------------------------------------------------------- */

    private function find(mixed $id): ?User
    {
        return is_numeric($id) ? auth()->getProvider()->findById((int) $id) : null;
    }

    /**
     * How many people have the superadmin role.
     */
    private function superadmins(): int
    {
        return (int) (service('roles')->find('superadmin')['people'] ?? 0);
    }

    /**
     * What the form posted, cleaned.
     *
     * @param array<string, mixed> $post
     *
     * @return array{username: string, email: string, roles: list<string>}
     */
    private function valuesFrom(array $post): array
    {
        return [
            'username' => trim((string) ($post['username'] ?? '')),
            'email'    => trim((string) ($post['email'] ?? '')),
            'roles'    => array_values(array_unique(array_map('strtolower', array_map('strval', is_array($post['roles'] ?? null) ? $post['roles'] : [])))),
        ];
    }

    /**
     * The roles a person ends up with. A role the person who saves may not handle is left as it was, and any other follows
     * the form. A name that is not a role is dropped.
     *
     * @param list<string> $current The roles before.
     * @param list<string> $posted  The roles the form asked for.
     *
     * @return list<string>
     */
    private function rolesAfter(array $current, array $posted): array
    {
        $permissions = service('permissions');
        $actor       = auth()->user();
        $known       = service('roles')->names();

        $kept = array_filter($current, static fn (string $role): bool => ! $permissions->mayAssignRole($actor, $role));
        $set  = array_filter($posted, static fn (string $role): bool => in_array($role, $known, true) && $permissions->mayAssignRole($actor, $role));

        return array_values(array_unique(array_merge($kept, $set)));
    }

    /**
     * The problems with what was posted, by place.
     *
     * @param array{username: string, email: string, roles: list<string>} $values
     *
     * @return array<string, string>
     */
    private function check(array $values, ?User $user, string $password): array
    {
        $errors = [];
        $db     = db_connect();

        if ($values['username'] === '' || mb_strlen($values['username']) < 3 || mb_strlen($values['username']) > 30 || preg_match('/^[A-Za-z0-9._-]+$/', $values['username']) !== 1) {
            $errors['username'] = lang('UserManagement.usernameInvalid');
        } elseif ($db->table('users')->where('username', $values['username'])->where('id !=', $user?->id ?? 0)->countAllResults() > 0) {
            $errors['username'] = lang('UserManagement.usernameTaken');
        }

        if (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = lang('UserManagement.emailInvalid');
        } elseif ($db->table('auth_identities')->where('type', 'email_password')->where('secret', $values['email'])->where('user_id !=', $user?->id ?? 0)->countAllResults() > 0) {
            $errors['email'] = lang('UserManagement.emailTaken');
        }

        // A new account needs a password, and a changed one must pass the rules of the site. The password rules look at the
        // name and the email, so they get an account that has them.
        if ($user === null || trim($password) !== '') {
            $subject = $user === null ? new User() : clone $user;
            $subject->fill(['username' => $values['username'], 'email' => $values['email']]);
            $result = service('passwords')->check($password, $subject);
            if (! $result->isOK()) {
                $errors['password'] = (string) $result->reason();
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function listData(array $input): array
    {
        $per   = (int) (service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 25);
        $found = (new UserList())->read($input, $per);
        $roles = array_column(service('roles')->all(), 'label', 'name');
        $types = FieldTypes::core();

        $rows = [];
        foreach ($found['rows'] as $row) {
            $edit  = base_url('admin/users/' . (int) $row['id'] . '/edit');
            $chips = '';
            foreach (array_filter(explode(',', (string) $row['roles'])) as $role) {
                $chips .= '<span class="chip">' . esc($roles[$role] ?? $role) . '</span>';
            }
            $status = $row['status'] === 'banned'
                ? '<span class="chip bad">' . esc(lang('UserManagement.statusBanned')) . '</span>'
                : ((int) $row['active'] === 0 ? '<span class="chip warn">' . esc(lang('UserManagement.statusInactive')) . '</span>' : '<span class="chip ok">' . esc(lang('UserManagement.statusActive')) . '</span>');
            $when = static fn (?string $value): string => $value === null || $value === '' ? '<span class="muted">' . esc(lang('UserManagement.never')) . '</span>' : $types->get('datetime')->cell($value, ['id' => 'x', 'type' => 'datetime']);

            $rows[] = [
                'id'      => (int) $row['id'],
                'label'   => (string) $row['username'],
                'cells'   => [
                    'username'    => '<a href="' . attr($edit) . '">' . esc((string) $row['username']) . '</a>',
                    'email'       => esc((string) $row['email']),
                    'roles'       => '<div class="chips">' . ($chips === '' ? '<span class="muted">' . esc(lang('UserManagement.noRoles')) . '</span>' : $chips) . '</div>',
                    'status'      => $status,
                    'last_active' => $when($row['last_active']),
                ],
                'actions' => ui('button', ['label' => lang('UserManagement.edit'), 'href' => $edit, 'variant' => 'quiet', 'size' => 'small']),
            ];
        }

        $request = $found['request'];
        $base    = base_url('admin/users');
        $url     = static function (array $override) use ($request, $base): string {
            $v     = array_merge($request, $override);
            $plain = $v['sort'] === 'username' && $v['dir'] === 'asc';
            $q     = array_filter(['q' => $v['q'], 'role' => $v['role'], 'sort' => $plain ? '' : $v['sort'], 'dir' => $plain ? '' : $v['dir'], 'per' => $v['per'] !== 25 ? $v['per'] : '', 'page' => (string) $v['page'] === '{page}' || $v['page'] > 1 ? $v['page'] : ''], static fn ($x): bool => $x !== '' && $x !== null);

            return $base . ($q === [] ? '' : '?' . str_replace(['%7Bpage%7D'], ['{page}'], http_build_query($q)));
        };

        $sorted = static function (string $key, string $label, string $class = '') use ($request, $url): array {
            $current = $request['sort'] === $key;
            $next    = $current && $request['dir'] === 'asc' ? 'desc' : 'asc';

            return ['key' => $key, 'label' => $label, 'class' => $class, 'href' => $url(['sort' => $key, 'dir' => $next, 'page' => 1]), 'dir' => $current ? $request['dir'] : null];
        };

        return [
            'request'     => $request,
            'total'       => $found['total'],
            'rows'        => $rows,
            'columns'     => [
                $sorted('username', lang('UserManagement.username'), 'label-cell'),
                $sorted('email', lang('UserManagement.email')),
                ['key' => 'roles', 'label' => lang('UserManagement.roles')],
                ['key' => 'status', 'label' => lang('UserManagement.status')],
                $sorted('last_active', lang('UserManagement.lastActive')),
            ],
            'htmx'        => ['hx-target' => '#users', 'hx-swap' => 'outerHTML', 'hx-push-url' => 'true'],
            'urls'        => ['list' => $base, 'page' => $url(['page' => '{page}']), 'new' => $base . '/new'],
            'roleOptions' => $roles,
        ];
    }

    /**
     * @param array<string, mixed>  $values
     * @param array<string, string> $errors
     */
    private function form(?User $user, array $values, array $errors, int $status = 200)
    {
        $permissions = service('permissions');
        $actor       = auth()->user();

        $roles = [];
        foreach (service('roles')->all() as $role) {
            $roles[] = $role + ['allowed' => $permissions->mayAssignRole($actor, $role['name']), 'has' => in_array($role['name'], $values['roles'], true)];
        }

        $title = $user === null ? lang('UserManagement.newUser') : (string) $user->username;

        return $this->response->setStatusCode($status)->setBody($this->page('UserManagement\Views\user_form', [
            'title'    => $title,
            'crumbs'   => [[lang('UserManagement.moduleName'), base_url('admin/users')], [$title, null]],
            'account'  => $user,
            'values'   => $values,
            'errors'   => $errors,
            'roles'    => $roles,
            'isSelf'   => $user !== null && (int) $user->id === (int) auth()->id(),
            'editable' => $user === null || $permissions->mayManageUser($actor, $user),
            'banned'   => $user !== null && $user->isBanned(),
            'banNote'  => $user !== null ? (string) ($user->getBanMessage() ?? '') : '',
            'last'     => $user !== null && $user->inGroup('superadmin') && $this->superadmins() <= 1,
            'formUrl'  => $user === null ? base_url('admin/users') : base_url('admin/users/' . (int) $user->id),
            'base'     => $user === null ? '' : base_url('admin/users/' . (int) $user->id),
        ]));
    }
}
