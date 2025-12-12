<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ModelUserGroupFilter
 *
 * A CodeIgniter filter that enforces per-model access control based on user groups.
 *
 * Responsibilities:
 *  - Ensure the request is authenticated. If not authenticated, redirect to the login route
 *    with an error message.
 *  - Extract a resource ID from the request URI at a configurable segment index.
 *  - Load the corresponding model record (via StarDust\Models\ModelsModel->getCustomBuilder()) and
 *    read its "user_groups" JSON field.
 *  - If the model defines user group restrictions, verify the current user's groups intersect
 *    with the model's allowed groups. If there is no intersection, deny access by redirecting
 *    back with an authorization error message.
 *
 * Expected environment and conventions:
 *  - The current user is available via auth()->user() and exposes getGroups() returning an array.
 *  - The model record stores a JSON array in the 'user_groups' column.
 *  - Redirect helpers (redirect(), route_to()) and language keys (lang('Auth.notEnoughPrivilege')) are used.
 *
 * How to configure via filter arguments:
 *  - $arguments[0] (string|null): Route or model identifier (the code only checks presence; adjust as needed).
 *  - $arguments[1] (int|null): Zero-based URI segment index that contains the resource ID. Defaults to 2
 *    (i.e., the third segment). The implementation calls $request->getUri()->getSegment($index + 1).
 *
 * Failure and return behavior:
 *  - Returns a RedirectResponse when:
 *      * the user is not authenticated (redirects to login with 'Please login'),
 *      * the $route argument is empty (redirects back with 'Invalid resource'),
 *      * the user is not permitted to access the model (redirects back with Auth.notEnoughPrivilege).
 *  - Returns null to allow the request to continue when access is allowed or when no actionable ID
 *    or user group restrictions are present.
 *
 * Notes & caveats:
 *  - The filter silently allows the request to continue if the ID segment is missing or non-numeric.
 *  - It decodes the model's 'user_groups' field with json_decode; ensure stored data is valid JSON.
 *  - Adjust the route/argument semantics if you expect different routing patterns or ID locations.
 */
class ModelUserGroupFilter implements FilterInterface
{


    /**
     * Before
     *
     * Perform authentication and authorization checks before the controller is executed.
     *
     * @param \CodeIgniter\HTTP\RequestInterface $request The current request instance.
     * @param array|null $arguments Optional filter arguments described above.
     * @return \CodeIgniter\HTTP\RedirectResponse|null Returns a redirect response on authentication/authorization failure,
     *         or null to continue normal execution.
     *
     * Side effects:
     *  - Reads a URI segment to determine the resource ID.
     *  - Loads the model via model('StarDust\Models\ModelsModel') and uses getCustomBuilder() to fetch the row.
     *  - Decodes 'user_groups' from the model and compares against $user->getGroups().
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth()->user();

        // Redirect to login if not authenticated
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login');
        }

        // Extract filter arguments: [Model Class, ID Segment Index]
        $route = $arguments[0] ?? null;
        $idSegmentIndex = $arguments[1] ?? 2; // Default to segment 3 (2 + 1)

        // Get ID from URI segment (e.g., /admin/model/123 → ID in segment 3)
        $id = $request->getUri()->getSegment($idSegmentIndex + 1);

        if (empty($id) || !is_numeric($id)) {
            return;
        }

        if (empty($route)) {
            return redirect()->route('dashboard')->with('error', lang('Filter.invalidResource'));
        }

        /** @var \StarDust\Models\ModelsModel */
        $modelsModel = model('StarDust\Models\ModelsModel');
        $modelsBuilder = $modelsModel->getCustomBuilder();

        // Retrieve the model record as an associative array.
        $model = $modelsBuilder->where('id', $id)->get()->getRowArray();

        // log_message('debug', "ModelUserGroupFilter: " . json_encode($model, JSON_PRETTY_PRINT));

        if (empty(json_decode($model['user_groups'] ?? '[]'))) {
            return;
        }

        $isAllowed = !empty(array_intersect(json_decode($model['user_groups'] ?? '[]'), $user->getGroups()));
        // dd($isAllowed, json_decode($model['user_groups'] ?? '[]'), $user->getGroups());

        if (!$isAllowed) {
            // User does not have permission to access this model.
            return redirect()->route('dashboard')
                ->with('error', lang('Auth.notEnoughPrivilege'));
        }
    }

    /**
     * After
     *
     * No post-processing is required for this filter.
     *
     * @param \CodeIgniter\HTTP\RequestInterface  $request  The current request instance.
     * @param \CodeIgniter\HTTP\ResponseInterface $response The response produced by the controller.
     * @param array|null                          $arguments Optional filter arguments.
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing required here for now.
    }
}
