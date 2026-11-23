<?php

namespace App\Data;

/**
 * Turns the `user_groups` of a model, the list of groups that may open it, into the actions each role gets on it.
 *
 * Every listed group may read the model and see its history. Creating, changing and deleting entries stays with the
 * groups that could write before (`superadmin`, `admin` and `developer`), so a listed group such as `beta` does not
 * gain what it never had. An empty list meant every signed-in user, and now gives every role except `user`.
 *
 * A superadmin has every action on every model and needs no row.
 */
final class ModelGrants
{
    /**
     * The roles that could write entries.
     */
    public const WRITERS = ['superadmin', 'admin', 'developer'];

    /**
     * @param list<string> $groups   The `user_groups` of the model.
     * @param list<string> $allRoles The names of every role there is.
     *
     * @return list<array{role: string, action: string}> The rows of `hyper_model_permissions`, without the model id.
     */
    public static function fromGroups(array $groups, array $allRoles): array
    {
        $roles = $groups === [] ? array_values(array_diff($allRoles, ['user'])) : array_values(array_unique(array_filter($groups, 'is_string')));
        $roles = array_values(array_diff($roles, ['superadmin']));

        $rows = [];
        foreach ($roles as $role) {
            foreach (Abilities::MODEL_ACTIONS as $action) {
                $writes = in_array($action, ['create', 'update', 'delete'], true);
                if ($writes && ! in_array($role, self::WRITERS, true)) {
                    continue;
                }
                $rows[] = ['role' => $role, 'action' => $action];
            }
        }

        return $rows;
    }
}
