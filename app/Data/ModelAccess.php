<?php

namespace App\Data;

use CodeIgniter\Shield\Entities\User;

/**
 * Who may read the entries of a model through the API, answered from the permissions of the roles of the user.
 * A visitor who is not signed in may read none.
 */
class ModelAccess
{
    public function __construct(private ModelStore $models, private Permissions $permissions)
    {
    }

    /**
     * Tell whether this user may read the entries of this model. Pass null for a visitor who is not signed in.
     */
    public function canRead(?User $user, int|string $modelId): bool
    {
        return $this->permissions->can($user, 'entries.read', (int) $modelId);
    }

    /**
     * List the ids of the models this user may not read, for leaving their entries out of a list.
     *
     * @return list<int>
     */
    public function hiddenIds(User $user): array
    {
        $hidden = [];
        foreach ($this->models->allIds() as $modelId) {
            if (! $this->canRead($user, $modelId)) {
                $hidden[] = $modelId;
            }
        }

        return $hidden;
    }
}
