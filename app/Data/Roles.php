<?php

namespace App\Data;

use CodeIgniter\Database\BaseConnection;

/**
 * The roles of the admin. A role is a name that never changes, a label and a description for the screens, and a flag for
 * a role that cannot be removed. A person's roles are the groups Shield keeps in `auth_groups_users`.
 */
class Roles
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Every role, with how many people have it.
     *
     * @return list<array{name: string, label: string, description: string, locked: bool, people: int}>
     */
    public function all(): array
    {
        $people = [];
        foreach ($this->db->query('SELECT `group`, COUNT(*) AS people FROM auth_groups_users GROUP BY `group`')->getResultArray() as $row) {
            $people[(string) $row['group']] = (int) $row['people'];
        }

        $roles = [];
        foreach ($this->db->table('hyper_roles')->orderBy('locked', 'DESC')->orderBy('name')->get()->getResultArray() as $row) {
            $roles[] = [
                'name'        => (string) $row['name'],
                'label'       => (string) $row['label'],
                'description' => (string) ($row['description'] ?? ''),
                'locked'      => (bool) $row['locked'],
                'people'      => $people[(string) $row['name']] ?? 0,
            ];
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_column($this->all(), 'name');
    }
}
