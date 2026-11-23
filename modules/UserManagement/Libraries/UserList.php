<?php

namespace UserManagement\Libraries;

/**
 * Reads the people for the list: a search over the name and the email, a filter by role, a sort, and a page.
 */
final class UserList
{
    private const SORTS = ['username' => 'users.username', 'email' => 'email', 'last_active' => 'users.last_active', 'created_at' => 'users.created_at'];

    /**
     * @param array<string, mixed> $input The query string, or the values of a form that carries the same names.
     *
     * @return array{request: array<string, mixed>, rows: list<array<string, mixed>>, total: int}
     */
    public function read(array $input, int $defaultPerPage): array
    {
        $q    = trim((string) ($input['q'] ?? ''));
        $role = trim((string) ($input['role'] ?? ''));
        $sort = array_key_exists($input['sort'] ?? '', self::SORTS) ? (string) $input['sort'] : 'username';
        $dir  = ($input['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $per  = in_array((int) ($input['per'] ?? 0), [10, 25, 50, 100], true) ? (int) $input['per'] : $defaultPerPage;
        $page = max(1, (int) ($input['page'] ?? 1));

        $builder = db_connect()->table('users')
            ->select('users.id, users.username, users.status, users.active, users.last_active, users.created_at, MAX(auth_identities.secret) AS email, COALESCE(GROUP_CONCAT(DISTINCT auth_groups_users.group ORDER BY auth_groups_users.group SEPARATOR ","), "") AS roles')
            ->join('auth_identities', "auth_identities.user_id = users.id AND auth_identities.type = 'email_password'", 'left')
            ->join('auth_groups_users', 'auth_groups_users.user_id = users.id', 'left')
            ->where('users.deleted_at', null)
            ->groupBy('users.id');

        if ($q !== '') {
            $builder->groupStart()->like('users.username', $q)->orLike('auth_identities.secret', $q)->groupEnd();
        }
        if ($role !== '') {
            $builder->having('FIND_IN_SET(' . db_connect()->escape($role) . ', roles) > 0', null, false);
        }

        $total = count($builder->get(null, 0, false)->getResultArray());
        $rows  = $builder->orderBy(self::SORTS[$sort], $dir)->orderBy('users.id', 'asc')->limit($per, ($page - 1) * $per)->get()->getResultArray();

        return [
            'request' => ['q' => $q, 'role' => $role, 'sort' => $sort, 'dir' => $dir, 'per' => $per, 'page' => $page],
            'rows'    => $rows,
            'total'   => $total,
        ];
    }
}
