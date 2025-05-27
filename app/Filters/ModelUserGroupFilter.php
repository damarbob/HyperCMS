<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ModelUserGroupFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth()->user();

        // Redirect to login if not authenticated
        if (!$user) {
            return redirect()->to(route_to('login'))->with('error', 'Please login');
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
            return redirect()->back()->with('error', 'Invalid resource');
        }

        /** @var \App\Models\ModelsModel */
        $modelsModel = model('App\Models\ModelsModel');
        $modelsBuilder = $modelsModel->getCustomBuilder();

        // Retrieve the model record as an associative array.
        $model = $modelsBuilder->where('id', $id)->get()->getRowArray();

        log_message('debug', "Model: " . json_encode($model, JSON_PRETTY_PRINT));

        if (empty(json_decode($model['user_groups'] ?? '[]'))) {
            return;
        }

        $isAllowed = !empty(array_intersect(json_decode($model['user_groups'] ?? '[]'), $user->getGroups()));
        // dd($isAllowed, json_decode($model['user_groups'] ?? '[]'), $user->getGroups());

        if (!$isAllowed) {
            // User does not have permission to access this model.
            return redirect()->back()
                ->with('error', lang('Auth.notEnoughPrivilege'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing required here for now.
    }
}
