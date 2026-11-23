<?php

namespace App\Data;

use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;

/**
 * The one place that answers whether a user may do something in the admin.
 *
 * Routes and views ask `can('entries.update', $modelId)` and never name a group. The answer comes from the roles a user
 * has, which are the groups Shield stores for the user, and from what each role is allowed in the database.
 *
 * - A global ability, such as `media.manage`, is allowed when one of the user's roles has it in `hyper_role_abilities`.
 * - An action on a model, `entries.read`, `entries.create`, `entries.update`, `entries.delete` or `entries.history`, is
 *   allowed when one of the user's roles has that action on that model in `hyper_model_permissions`.
 * - A superadmin may do everything, including what an admin added by hand, and no screen can change that.
 */
class Permissions
{
    /**
     * The role that may do everything.
     */
    public const SUPERADMIN = 'superadmin';

    public function __construct(private GrantSource $grants)
    {
    }

    /**
     * @param User|null       $user    The user, or null for a visitor who is not signed in, who may do nothing.
     * @param int|string|null $modelId The model, for the abilities that are about one.
     *
     * @throws InvalidArgumentException When the ability is unknown, or needs a model id and has none. A typo must fail
     *                                  loudly and never read as a refusal.
     */
    public function can(?User $user, string $ability, int|string|null $modelId = null): bool
    {
        $about = Abilities::isModelAbility($ability);

        if (! $about && ! $this->grants->known($ability)) {
            throw new InvalidArgumentException("Unknown permission '$ability'.");
        }
        if ($about && ($modelId === null || $modelId === '')) {
            throw new InvalidArgumentException("The permission '$ability' is about one model, so it needs a model id.");
        }

        if ($user === null) {
            return false;
        }

        $roles = array_map('strtolower', $user->getGroups() ?? []);
        if (in_array(self::SUPERADMIN, $roles, true)) {
            return true;
        }

        $holders = $about
            ? $this->grants->rolesWithModelAction((int) $modelId, substr($ability, 8))
            : $this->grants->rolesWithAbility($ability);

        return array_intersect($roles, $holders) !== [];
    }

    /**
     * Whether a person may give a role to someone, or take it away.
     *
     * A superadmin may do either with any role. Anyone else may only handle a role whose abilities, and whose actions
     * on every model, they have themselves, and never the superadmin role. Without this, whoever may manage users, or
     * edit roles, could make themselves or a friend a superadmin.
     */
    public function mayAssignRole(?User $actor, string $role): bool
    {
        if ($actor === null) {
            return false;
        }

        $mine = array_map('strtolower', $actor->getGroups() ?? []);
        if (in_array(self::SUPERADMIN, $mine, true)) {
            return true;
        }
        if ($role === self::SUPERADMIN) {
            return false;
        }

        foreach ($this->grants->abilitiesOfRole($role) as $ability) {
            if (array_intersect($mine, $this->grants->rolesWithAbility($ability)) === []) {
                return false;
            }
        }
        foreach ($this->grants->modelActionsOfRole($role) as $modelId => $actions) {
            foreach ($actions as $action) {
                if (array_intersect($mine, $this->grants->rolesWithModelAction($modelId, $action)) === []) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Whether a person may change or remove the account of another person, which they may when they may handle every
     * role that person has.
     */
    public function mayManageUser(?User $actor, User $target): bool
    {
        foreach ($target->getGroups() ?? [] as $role) {
            if (! $this->mayAssignRole($actor, strtolower($role))) {
                return false;
            }
        }

        return $actor !== null;
    }
}
