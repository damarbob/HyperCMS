<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Data\WriteVersions;
use CodeIgniter\Shield\Entities\User;

/**
 * The account of the signed-in person: name, email, password, and what protects the sign in. Changing the email or the
 * password asks for the current password first, so someone at an unlocked screen cannot take the account over.
 */
class Profile extends AdminController
{
    public function index()
    {
        $user = auth()->user();

        return $this->form(['username' => (string) $user->username, 'email' => (string) $user->email], []);
    }

    public function update()
    {
        $user = auth()->user();
        $post = $this->request->getPost();

        $values   = ['username' => trim((string) ($post['username'] ?? '')), 'email' => trim((string) ($post['email'] ?? ''))];
        $password = (string) ($post['password'] ?? '');
        $current  = (string) ($post['current_password'] ?? '');
        $errors   = [];
        $db       = db_connect();

        if ($values['username'] === '' || mb_strlen($values['username']) < 3 || mb_strlen($values['username']) > 30 || preg_match('/^[A-Za-z0-9._-]+$/', $values['username']) !== 1) {
            $errors['username'] = lang('Profile.usernameInvalid');
        } elseif ($db->table('users')->where('username', $values['username'])->where('id !=', $user->id)->countAllResults() > 0) {
            $errors['username'] = lang('Profile.usernameTaken');
        }

        if (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = lang('Profile.emailInvalid');
        } elseif ($db->table('auth_identities')->where('type', 'email_password')->where('secret', $values['email'])->where('user_id !=', $user->id)->countAllResults() > 0) {
            $errors['email'] = lang('Profile.emailTaken');
        }

        $changesSecret = $values['email'] !== (string) $user->email || $password !== '';
        if ($changesSecret && ! auth()->check(['email' => (string) $user->email, 'password' => $current])->isOK()) {
            $errors['current_password'] = lang('Profile.currentPasswordWrong');
        }

        if ($password !== '') {
            $subject = clone $user;
            $subject->fill(['username' => $values['username'], 'email' => $values['email']]);
            $result = service('passwords')->check($password, $subject);
            if (! $result->isOK()) {
                $errors['password'] = (string) $result->reason();
            }
        }

        if ($errors !== []) {
            return $this->form($values, $errors, 422);
        }

        $users       = auth()->getProvider();
        $account     = $users->findById($user->id);
        $oldUsername = $account->username;
        $account->fill($values + ($password !== '' ? ['password' => $password] : []));

        try {
            $users->save($account);
        } catch (\Throwable $e) {
            return $this->form($values, ['general' => $e->getMessage()], 500);
        }

        // Lists show usernames and a saved list may hold matches or an order built from the old one, so a rename raises
        // the global write version and the next list request builds a fresh list.
        if ($account->username !== $oldUsername) {
            (new WriteVersions())->bumpGlobal();
        }

        return redirect()->to(base_url('admin/profile'))->with('success', lang('Profile.saved'));
    }

    /**
     * @param array{username: string, email: string} $values
     * @param array<string, string>                  $errors
     */
    private function form(array $values, array $errors, int $status = 200)
    {
        $user   = auth()->user();
        $labels = array_column(service('roles')->all(), 'label', 'name');

        $this->data = array_merge($this->data, [
            'title'     => lang('Profile.title'),
            'crumbs'    => [[lang('Profile.title'), null]],
            'account'   => $user,
            'values'    => $values,
            'errors'    => $errors,
            'roles'     => array_map(static fn (string $role): string => $labels[strtolower($role)] ?? $role, $user->getGroups() ?? []),
            'twoFactor' => (bool) service('settings')->get('Hyper.twoFactor'),
            'tokens'    => $this->may('tokens.manage'),
        ]);

        return $this->response->setStatusCode($status)->setBody(view('admin/profile', $this->data));
    }

    private function may(string $ability): bool
    {
        try {
            return can($ability);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
