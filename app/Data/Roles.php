<?php

namespace App\Data;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

/**
 * The roles of the admin. A role is a name that never changes, a label and a description for the screens, and a flag for
 * a role that cannot be removed. A person's roles are the groups Shield keeps in `auth_groups_users`.
 *
 * What a role may do is two lists: the global abilities in `hyper_role_abilities`, and the actions on each model in
 * `hyper_model_permissions`. The `superadmin` role is locked and has everything, so neither list is kept for it.
 */
class Roles
{
    /**
     * The shape of a role name: lowercase letters, numbers, dashes and underscores, starting with a letter.
     */
    public const NAME = '/^[a-z][a-z0-9_-]{1,31}$/';

    public function __construct(private readonly BaseConnection $db, private readonly ?Grants $grants = null)
    {
    }

    /**
     * Every role, with how many people have it.
     *
     * @return list<array{name: string, label: string, description: string, locked: bool, people: int, abilities: int}>
     */
    public function all(): array
    {
        $people = [];
        foreach ($this->db->query('SELECT `group`, COUNT(*) AS people FROM ' . $this->db->prefixTable('auth_groups_users') . ' GROUP BY `group`')->getResultArray() as $row) {
            $people[(string) $row['group']] = (int) $row['people'];
        }
        $abilities = [];
        foreach ($this->db->query('SELECT role, COUNT(*) AS abilities FROM ' . $this->db->prefixTable('hyper_role_abilities') . ' GROUP BY role')->getResultArray() as $row) {
            $abilities[(string) $row['role']] = (int) $row['abilities'];
        }

        $roles = [];
        foreach ($this->db->table('hyper_roles')->orderBy('locked', 'DESC')->orderBy('name')->get()->getResultArray() as $row) {
            $roles[] = [
                'name'        => (string) $row['name'],
                'label'       => (string) $row['label'],
                'description' => (string) ($row['description'] ?? ''),
                'locked'      => (bool) $row['locked'],
                'people'      => $people[(string) $row['name']] ?? 0,
                'abilities'   => $abilities[(string) $row['name']] ?? 0,
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

    /**
     * @return array{name: string, label: string, description: string, locked: bool, people: int, abilities: int}|null
     */
    public function find(string $name): ?array
    {
        foreach ($this->all() as $role) {
            if ($role['name'] === $name) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Make a role, and give it what another role has when one is named.
     *
     * @throws InvalidArgumentException When the name is not a name, is taken, or the label is empty. The message is for the person.
     */
    public function create(string $name, string $label, string $description, ?string $copyFrom = null): void
    {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidArgumentException(lang('Roles.nameInvalid'));
        }
        if ($this->find($name) !== null) {
            throw new InvalidArgumentException(lang('Roles.nameTaken', ['name' => $name]));
        }
        if (trim($label) === '') {
            throw new InvalidArgumentException(lang('Roles.labelRequired'));
        }

        $now = date('Y-m-d H:i:s');
        $this->db->transStart();
        $this->db->table('hyper_roles')->insert(['name' => $name, 'label' => trim($label), 'description' => trim($description) === '' ? null : trim($description), 'locked' => 0, 'created_at' => $now, 'updated_at' => $now]);

        if ($copyFrom !== null && $copyFrom !== '' && $copyFrom !== Permissions::SUPERADMIN && $this->find($copyFrom) !== null) {
            $abilities   = $this->db->prefixTable('hyper_role_abilities');
            $permissions = $this->db->prefixTable('hyper_model_permissions');
            $this->db->query("INSERT INTO {$abilities} (role, ability) SELECT ?, ability FROM {$abilities} WHERE role = ?", [$name, $copyFrom]);
            $this->db->query("INSERT INTO {$permissions} (model_id, role, action) SELECT model_id, ?, action FROM {$permissions} WHERE role = ?", [$name, $copyFrom]);
        }
        $this->db->transComplete();
        $this->grants?->forget();
    }

    /**
     * Change the label and the description of a role. The name stays, because people and permissions refer to it.
     *
     * @throws InvalidArgumentException When the role does not exist or the label is empty.
     */
    public function update(string $name, string $label, string $description): void
    {
        if ($this->find($name) === null) {
            throw new InvalidArgumentException(lang('Roles.notFound'));
        }
        if (trim($label) === '') {
            throw new InvalidArgumentException(lang('Roles.labelRequired'));
        }

        $this->db->table('hyper_roles')->where('name', $name)->update(['label' => trim($label), 'description' => trim($description) === '' ? null : trim($description), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Remove a role and what it may do.
     *
     * @throws InvalidArgumentException When the role is locked or still has people in it, or does not exist.
     */
    public function delete(string $name): void
    {
        $role = $this->find($name);
        if ($role === null) {
            throw new InvalidArgumentException(lang('Roles.notFound'));
        }
        if ($role['locked']) {
            throw new InvalidArgumentException(lang('Roles.lockedCannotDelete', ['name' => $role['label']]));
        }
        if ($role['people'] > 0) {
            throw new InvalidArgumentException(lang('Roles.hasPeople', ['name' => $role['label'], 'count' => $role['people']]));
        }

        $this->db->transStart();
        $this->db->table('hyper_role_abilities')->where('role', $name)->delete();
        $this->db->table('hyper_model_permissions')->where('role', $name)->delete();
        $this->db->table('hyper_roles')->where('name', $name)->delete();
        $this->db->transComplete();
        $this->grants?->forget();
    }

    /**
     * The global abilities a role has.
     *
     * @return list<string>
     */
    public function abilitiesOf(string $name): array
    {
        return array_column($this->db->table('hyper_role_abilities')->select('ability')->where('role', $name)->orderBy('ability')->get()->getResultArray(), 'ability');
    }

    /**
     * Replace the global abilities of a role. A name that is not an ability is ignored. The locked role has everything
     * and keeps no list.
     *
     * @param list<string> $abilities
     * @param list<string> $known     The names of the abilities that exist.
     */
    public function setAbilities(string $name, array $abilities, array $known): void
    {
        if ($name === Permissions::SUPERADMIN) {
            return;
        }

        $this->db->transStart();
        $this->db->table('hyper_role_abilities')->where('role', $name)->delete();
        foreach (array_unique(array_intersect($abilities, $known)) as $ability) {
            $this->db->table('hyper_role_abilities')->insert(['role' => $name, 'ability' => $ability]);
        }
        $this->db->transComplete();
        $this->grants?->forget();
    }

    /**
     * What a role may do on each model.
     *
     * @return array<int, list<string>> The actions by model id.
     */
    public function modelActionsOf(string $name): array
    {
        $by = [];
        foreach ($this->db->table('hyper_model_permissions')->where('role', $name)->orderBy('model_id')->get()->getResultArray() as $row) {
            $by[(int) $row['model_id']][] = (string) $row['action'];
        }

        return $by;
    }

    /**
     * Replace what a role may do on every model. Anything other than reading also lets the role read, and a model that
     * does not exist is ignored.
     *
     * @param array<int|string, list<string>> $byModel The actions by model id.
     * @param list<int>                       $modelIds The ids of the models that exist.
     */
    public function setModelActions(string $name, array $byModel, array $modelIds): void
    {
        if ($name === Permissions::SUPERADMIN) {
            return;
        }

        $this->db->transStart();
        $this->db->table('hyper_model_permissions')->where('role', $name)->delete();
        foreach ($byModel as $modelId => $actions) {
            if (! in_array((int) $modelId, $modelIds, true) || ! is_array($actions)) {
                continue;
            }
            $actions = array_values(array_intersect(Abilities::MODEL_ACTIONS, array_map('strval', $actions)));
            if ($actions === []) {
                continue;
            }
            if (! in_array('read', $actions, true)) {
                array_unshift($actions, 'read');
            }
            foreach ($actions as $action) {
                $this->db->table('hyper_model_permissions')->insert(['model_id' => (int) $modelId, 'role' => $name, 'action' => $action]);
            }
        }
        $this->db->transComplete();
        $this->grants?->forget();
    }
}
