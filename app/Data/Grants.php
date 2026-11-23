<?php

namespace App\Data;

use CodeIgniter\Database\BaseConnection;

/**
 * What each role may do, read from `hyper_role_abilities` and `hyper_model_permissions`, and written to them.
 *
 * Reads are kept for the rest of the request, and every write forgets them.
 */
class Grants implements GrantSource
{
    /**
     * @var array<string, list<string>>|null Roles by global ability.
     */
    private ?array $abilityRoles = null;

    /**
     * @var array<int, array<string, list<string>>>|null Roles by action, by model id.
     */
    private ?array $modelRoles = null;

    public function __construct(private readonly BaseConnection $db, private readonly Abilities $abilities)
    {
    }

    public function known(string $ability): bool
    {
        return Abilities::isModelAbility($ability) || $this->abilities->exists($ability);
    }

    public function rolesWithAbility(string $ability): array
    {
        if ($this->abilityRoles === null) {
            $this->abilityRoles = [];
            foreach ($this->db->table('hyper_role_abilities')->get()->getResultArray() as $row) {
                $this->abilityRoles[(string) $row['ability']][] = (string) $row['role'];
            }
        }

        return $this->abilityRoles[$ability] ?? [];
    }

    public function rolesWithModelAction(int $modelId, string $action): array
    {
        if ($this->modelRoles === null) {
            $this->modelRoles = [];
            foreach ($this->db->table('hyper_model_permissions')->get()->getResultArray() as $row) {
                $this->modelRoles[(int) $row['model_id']][(string) $row['action']][] = (string) $row['role'];
            }
        }

        return $this->modelRoles[$modelId][$action] ?? [];
    }

    /**
     * What roles may do on one model, as the actions of each role.
     *
     * @return array<string, list<string>>
     */
    public function forModel(int $modelId): array
    {
        $byRole = [];
        foreach ($this->db->table('hyper_model_permissions')->where('model_id', $modelId)->orderBy('role')->get()->getResultArray() as $row) {
            $byRole[(string) $row['role']][] = (string) $row['action'];
        }

        return $byRole;
    }

    /**
     * Replace what roles may do on a model.
     *
     * @param array<string, list<string>> $byRole The actions of each role. An action that is not one of `Abilities::MODEL_ACTIONS` is ignored.
     */
    public function setForModel(int $modelId, array $byRole): void
    {
        $this->db->transStart();
        $this->db->table('hyper_model_permissions')->where('model_id', $modelId)->delete();
        foreach ($byRole as $role => $actions) {
            foreach (array_unique($actions) as $action) {
                if (in_array($action, Abilities::MODEL_ACTIONS, true)) {
                    $this->db->table('hyper_model_permissions')->insert(['model_id' => $modelId, 'role' => (string) $role, 'action' => $action]);
                }
            }
        }
        $this->db->transComplete();
        $this->forget();
    }

    /**
     * Give the roles of a model what its `user_groups` mean, as `ModelGrants` reads them. This is how a model made in
     * the builder gets its permissions until the builder has a grid of its own.
     *
     * @param list<string> $groups
     */
    public function setFromGroups(int $modelId, array $groups): void
    {
        $roles = array_column($this->db->table('hyper_roles')->select('name')->get()->getResultArray(), 'name');

        $byRole = [];
        foreach (ModelGrants::fromGroups($groups, $roles) as $row) {
            $byRole[$row['role']][] = $row['action'];
        }
        $this->setForModel($modelId, $byRole);
    }

    /**
     * Forget everything a model has, when it is deleted for good.
     */
    public function forgetModel(int $modelId): void
    {
        $this->db->table('hyper_model_permissions')->where('model_id', $modelId)->delete();
        $this->forget();
    }

    public function forget(): void
    {
        $this->abilityRoles = null;
        $this->modelRoles   = null;
    }
}
