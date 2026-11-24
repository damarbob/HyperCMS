<?php

namespace UserManagement\Libraries;

/**
 * Reads the people for the list: a search over the name and the email, a filter by role, a sort, and a page.
 */
final class UserList
{
    // The tables carry aliases, because a table name inside an expression is not given the site's table prefix.
    private const SORTS = ['username' => 'u.username', 'email' => 'email', 'last_active' => 'u.last_active', 'created_at' => 'u.created_at'];

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

        $builder = db_connect()->table('users u')
            ->select('u.id, u.username, u.status, u.active, u.last_active, u.created_at, MAX(i.secret) AS email, COALESCE(GROUP_CONCAT(DISTINCT g.group ORDER BY g.group SEPARATOR ","), "") AS roles', false)
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->join('auth_groups_users g', 'g.user_id = u.id', 'left')
            ->where('u.deleted_at', null)
            ->groupBy('u.id');

        if ($q !== '') {
            $builder->groupStart()->like('u.username', $q)->orLike('i.secret', $q)->groupEnd();
        }
        if ($role !== '') {
            $builder->having('FIND_IN_SET(' . db_connect()->escape($role) . ', roles) > 0', null, false);
        }

        $total = count($builder->get(null, 0, false)->getResultArray());
        $rows  = $builder->orderBy(self::SORTS[$sort], $dir)->orderBy('u.id', 'asc')->limit($per, ($page - 1) * $per)->get()->getResultArray();

        return [
            'request' => ['q' => $q, 'role' => $role, 'sort' => $sort, 'dir' => $dir, 'per' => $per, 'page' => $page],
            'rows'    => $rows,
            'total'   => $total,
        ];
    }
}
