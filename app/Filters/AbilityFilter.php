<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

/**
 * Lets a request through only when the signed-in user has an ability, as `can()` answers it.
 *
 * `can:media.manage` asks a global ability. `can:entries.read,2` asks an action on a model, and the number is the place
 * of the model id in the address, counted from zero, so `admin/model/12` has it at 2. A request whose address has no
 * number at that place is refused, never let through, because a filter that cannot tell which model is being asked about
 * has to say no.
 *
 * Someone who is not signed in goes to the login page. Someone who is signed in and may not goes to the dashboard with a
 * message, and a request made by a script gets a 403 without a page.
 */
class AbilityFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->route('login')->with('error', lang('Filter.adminLoginRequired'));
        }

        $ability = (string) ($arguments[0] ?? '');
        $modelId = null;

        if (\App\Data\Abilities::isModelAbility($ability)) {
            $place   = (int) ($arguments[1] ?? 2);
            $segment = $request->getUri()->getSegment($place + 1);
            if ($segment === '' || ! ctype_digit($segment)) {
                return $this->refuse($request);
            }
            $modelId = (int) $segment;
        }

        try {
            $allowed = service('permissions')->can($user, $ability, $modelId);
        } catch (InvalidArgumentException) {
            // A route that names an ability nobody registered is a mistake in the route, and nobody gets through it.
            $allowed = false;
        }

        return $allowed ? null : $this->refuse($request);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function refuse(RequestInterface $request)
    {
        if ($request->hasHeader('X-Requested-With') || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)->setJSON(['error' => lang('Auth.notEnoughPrivilege')]);
        }

        return redirect()->route('dashboard')->with('error', lang('Auth.notEnoughPrivilege'));
    }
}
