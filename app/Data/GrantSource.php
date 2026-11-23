<?php

namespace App\Data;

/**
 * What `Permissions` needs to know to answer a question: which abilities exist, and which roles hold them.
 */
interface GrantSource
{
    /**
     * Whether the name is an ability a check may ask about.
     */
    public function known(string $ability): bool;

    /**
     * The roles that have a global ability. A superadmin is not listed, because it has every one.
     *
     * @return list<string>
     */
    public function rolesWithAbility(string $ability): array;

    /**
     * The roles that may do an action on a model. A superadmin is not listed.
     *
     * @return list<string>
     */
    public function rolesWithModelAction(int $modelId, string $action): array;
}
