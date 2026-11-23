<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User as ShieldUser;
use Throwable;

/**
 * Makes people, resets passwords and changes roles from the command line. It is the way back in when the last
 * superadmin password is lost: `php spark hyper:user password someone`, or `php spark hyper:user role add someone superadmin`.
 *
 * Whoever can run it already has the server, so it does not ask who is acting. It still follows the rules that keep a site
 * from locking itself out: the last superadmin keeps the role unless `--force` is given.
 */
class User extends BaseCommand
{
    protected $group       = 'Hyper';
    protected $name        = 'hyper:user';
    protected $description = 'Make a user, reset a password, ban a user, or change the roles of a user.';
    protected $usage       = 'hyper:user <action> [arguments] [options]';
    protected $arguments   = [
        'action' => 'list | create <username> <email> | password <user> | role add <user> <role> | role remove <user> <role> | ban <user> | unban <user>',
        'user'   => 'A username or an email address.',
    ];
    protected $options = [
        '--role'     => 'create: the role to give. Repeat the option for several.',
        '--password' => 'create and password: the password. When it is left out the command asks for it.',
        '--force'    => 'role remove: take the superadmin role from the last superadmin anyway.',
        '--message'  => 'ban: the reason the person sees.',
    ];

    public function run(array $params)
    {
        $action = $params[0] ?? '';

        try {
            return match ($action) {
                'list'     => $this->list(),
                'create'   => $this->create($params[1] ?? '', $params[2] ?? ''),
                'password' => $this->password($params[1] ?? ''),
                'role'     => $this->role($params[1] ?? '', $params[2] ?? '', $params[3] ?? ''),
                'ban'      => $this->ban($params[1] ?? '', true),
                'unban'    => $this->ban($params[1] ?? '', false),
                default    => $this->help(),
            };
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }
    }

    private function help(): int
    {
        CLI::write('Usage: php spark hyper:user <action> [arguments] [options]', 'yellow');
        foreach (explode(' | ', $this->arguments['action']) as $line) {
            CLI::write('  ' . $line);
        }

        return EXIT_ERROR;
    }

    private function list(): int
    {
        $rows = [];
        foreach (db_connect()->query('SELECT u.id, u.username, u.status, MAX(i.secret) AS email, GROUP_CONCAT(g.`group` ORDER BY g.`group`) AS roles FROM users u LEFT JOIN auth_identities i ON i.user_id = u.id AND i.type = "email_password" LEFT JOIN auth_groups_users g ON g.user_id = u.id WHERE u.deleted_at IS NULL GROUP BY u.id ORDER BY u.id')->getResultArray() as $row) {
            $rows[] = [(string) $row['id'], (string) $row['username'], (string) $row['email'], (string) ($row['roles'] ?? ''), $row['status'] === 'banned' ? 'banned' : 'ok'];
        }
        CLI::table($rows, ['Id', 'Username', 'Email', 'Roles', 'Status']);

        return EXIT_SUCCESS;
    }

    private function create(string $username, string $email): int
    {
        if ($username === '' || $email === '') {
            CLI::error('Usage: php spark hyper:user create <username> <email> [--role superadmin] [--password <password>]');

            return EXIT_ERROR;
        }

        $roles = CLI::getOption('role');
        $roles = $roles === null || $roles === true ? [] : (array) $roles;
        foreach ($roles as $role) {
            if (service('roles')->find((string) $role) === null) {
                CLI::error("There is no role '$role'. See php spark hyper:role list.");

                return EXIT_ERROR;
            }
        }

        if (db_connect()->table('users')->where('username', $username)->countAllResults() > 0) {
            CLI::error("There is already a user named '$username'.");

            return EXIT_ERROR;
        }

        $user     = new ShieldUser(['username' => $username, 'email' => $email]);
        $password = $this->passwordFor($user);

        $users = auth()->getProvider();
        $users->save(new ShieldUser(['username' => $username, 'email' => $email, 'password' => $password]));
        $created = $users->findById($users->getInsertID());
        $created->syncGroups(...array_map('strval', $roles));
        $created->activate();

        CLI::write("User '$username' is made" . ($roles === [] ? ' with no role.' : ' with the roles ' . implode(', ', $roles) . '.'), 'green');

        return EXIT_SUCCESS;
    }

    private function password(string $who): int
    {
        $user = $this->find($who);

        $password = $this->passwordFor($user);
        $user->fill(['password' => $password]);
        auth()->getProvider()->save($user);

        CLI::write("The password of '{$user->username}' is changed.", 'green');

        return EXIT_SUCCESS;
    }

    private function role(string $verb, string $who, string $role): int
    {
        if (! in_array($verb, ['add', 'remove'], true) || $who === '' || $role === '') {
            CLI::error('Usage: php spark hyper:user role add|remove <user> <role>');

            return EXIT_ERROR;
        }
        if (service('roles')->find($role) === null) {
            CLI::error("There is no role '$role'. See php spark hyper:role list.");

            return EXIT_ERROR;
        }

        $user = $this->find($who);

        if ($verb === 'add') {
            $user->addGroup($role);
            CLI::write("'{$user->username}' now has the role $role.", 'green');

            return EXIT_SUCCESS;
        }

        if ($role === 'superadmin' && $user->inGroup('superadmin') && (int) (service('roles')->find('superadmin')['people'] ?? 0) <= 1 && CLI::getOption('force') === null) {
            CLI::error('This is the last superadmin. Add another first, or give --force to take the role anyway.');

            return EXIT_ERROR;
        }
        $user->removeGroup($role);
        CLI::write("'{$user->username}' no longer has the role $role.", 'green');

        return EXIT_SUCCESS;
    }

    private function ban(string $who, bool $on): int
    {
        $user = $this->find($who);

        if ($on) {
            if ($user->inGroup('superadmin') && (int) (service('roles')->find('superadmin')['people'] ?? 0) <= 1) {
                CLI::error('This is the last superadmin, so the account is not banned.');

                return EXIT_ERROR;
            }
            $message = CLI::getOption('message');
            $user->ban(is_string($message) ? $message : null);
        } else {
            $user->unBan();
        }
        auth()->getProvider()->save($user);

        CLI::write("'{$user->username}' " . ($on ? 'is banned.' : 'can sign in again.'), 'green');

        return EXIT_SUCCESS;
    }

    private function find(string $who): ShieldUser
    {
        if ($who === '') {
            throw new \InvalidArgumentException('Name the user by username or email.');
        }

        $row = db_connect()->query('SELECT u.id FROM users u LEFT JOIN auth_identities i ON i.user_id = u.id AND i.type = "email_password" WHERE u.deleted_at IS NULL AND (u.username = ? OR i.secret = ?) LIMIT 1', [$who, $who])->getRowArray();
        $user = $row === null ? null : auth()->getProvider()->findById((int) $row['id']);
        if ($user === null) {
            throw new \InvalidArgumentException("There is no user '$who'. See php spark hyper:user list.");
        }

        return $user;
    }

    /**
     * The password from the option, or asked for, and checked against the rules of the site.
     */
    private function passwordFor(ShieldUser $user): string
    {
        $password = CLI::getOption('password');
        if (! is_string($password) || $password === '') {
            $password = CLI::prompt('Password (it shows as you type)');
        }

        $result = service('passwords')->check((string) $password, $user);
        if (! $result->isOK()) {
            throw new \InvalidArgumentException((string) $result->reason());
        }

        return (string) $password;
    }
}
