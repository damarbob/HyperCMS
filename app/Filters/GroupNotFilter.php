<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Filters\AbstractAuthFilter;

/**
 * GroupNot Authorization Filter.
 *
 * This filter ensures that the current user is NOT a member of any of the provided groups.
 * If the user is in any of the forbidden groups, the filter fails and the user is redirected.
 */
class GroupNotFilter extends AbstractAuthFilter
{
    /**
     * Checks that the current user is NOT in the provided groups.
     *
     * @param array $arguments A list of group names the user must NOT belong to.
     * @return bool Returns true if the user is NOT in any of those groups.
     */
    protected function isAuthorized(array $arguments): bool
    {
        // If user is in any of the forbidden groups, we return false.
        return ! auth()->user()->inGroup(...$arguments);
    }

    /**
     * Redirects the user when they belong to one of the forbidden groups.
     *
     * @return RedirectResponse
     */
    protected function redirectToDeniedUrl(): RedirectResponse
    {
        /** @var \Config\Auth */
        $auth = config('Auth');
        return redirect()->to($auth->groupDeniedRedirect())
            ->with('error', lang('Auth.notEnoughPrivilege'));
    }
}
