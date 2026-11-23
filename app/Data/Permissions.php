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
}
