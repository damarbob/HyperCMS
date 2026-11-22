<?php

namespace App\Data;

use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;

/**
 * The one place that answers whether a user may do something in the admin.
 *
 * Routes and views ask `can('entries.update', $modelId)` and never name a group. The answers follow the rules the
 * admin has today, so asking this class changes nobody's access.
 *
 * | Permission                                            | Allowed for today                                          |
 * | :--                                                   | :--                                                        |
 * | `models.manage`, `settings.manage`, `users.manage`, `entries.purge` | the `superadmin` group                       |
 * | `media.manage`                                        | every group except `user`                                  |
 * | `entries.read`, `entries.history` with a model id     | a user who may open the model                              |
 * | `entries.create`, `entries.update`, `entries.delete` with a model id | `superadmin`, `admin` or `developer`, and may open the model |
 *
 * A user may open a model when its `user_groups` is empty, or when the user is in one of the groups it names.
 */
class Permissions
{
    /**
     * Permissions that need no model.
     */
    public const GLOBAL = ['models.manage', 'settings.manage', 'users.manage', 'media.manage', 'entries.purge'];

    /**
     * Permissions that are about one model, and need its id.
     */
    public const MODEL = ['entries.read', 'entries.create', 'entries.update', 'entries.delete', 'entries.history'];

    /**
     * The groups that may write entries.
     */
    private const WRITERS = ['superadmin', 'admin', 'developer'];

    /**
     * Each model's groups by model id, read once per request.
     *
     * @var array<int, list<string>>|null
     */
    private ?array $groups = null;

    public function __construct(private ModelStore $models)
    {
    }

    /**
     * @param User|null       $user       The user, or null for a visitor who is not signed in, who may do nothing.
     * @param int|string|null $modelId    The model, for the permissions that are about one.
     *
     * @throws InvalidArgumentException When the permission is unknown, or needs a model id and has none. A typo must
     *                                  fail loudly and never read as a refusal.
     */
    public function can(?User $user, string $permission, int|string|null $modelId = null): bool
    {
        $global = in_array($permission, self::GLOBAL, true);
        if (! $global && ! in_array($permission, self::MODEL, true)) {
            throw new InvalidArgumentException("Unknown permission '$permission'.");
        }
        if (! $global && ($modelId === null || $modelId === '')) {
            throw new InvalidArgumentException("The permission '$permission' is about one model, so it needs a model id.");
        }

        if ($user === null) {
            return false;
        }

        return match ($permission) {
            'models.manage', 'settings.manage', 'users.manage', 'entries.purge' => $user->inGroup('superadmin'),
            'media.manage'                                                        => ! $user->inGroup('user'),
            'entries.read', 'entries.history'                                     => $this->mayOpen($user, (int) $modelId),
            default                                                               => $user->inGroup(...self::WRITERS) && $this->mayOpen($user, (int) $modelId),
        };
    }

    /**
     * Whether the model lets this user in. The model's groups are the ones in its `user_groups`.
     *
     * An id that names no model lets a signed-in user in, so the caller's own not-found answer still applies.
     */
    private function mayOpen(User $user, int $modelId): bool
    {
        $groups = $this->groups()[$modelId] ?? [];

        return $groups === [] || array_intersect($groups, $user->getGroups()) !== [];
    }

    /**
     * @return array<int, list<string>>
     */
    private function groups(): array
    {
        return $this->groups ??= $this->models->userGroups();
    }
}
