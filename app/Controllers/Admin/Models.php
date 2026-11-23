<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Libraries\ModelsPage;
use InvalidArgumentException;
use RuntimeException;

class Models extends AdminController
{
    public function index()
    {
        $data = (new ModelsPage())->build($this->request->getGet(), $this->perPage());

        // A model's list can be changed by a module through this filter, as the lists of entries are.
        $data = $this->hooks->filter(hook('Backend.controller:models:index:data'), $data);

        // A swap of the list gets the list alone. The browser's own restore of a page after Back needs the whole page.
        if (Model::wantsTheList($this->request, 'models')) {
            return view('admin/partials/model_results', $data);
        }

        $this->data = array_merge($this->data, $data, [
            'title'  => lang('Models.models'),
            'crumbs' => [[lang('Models.models'), null]],
        ]);

        return view('admin/models', $this->data);
    }

    public function new(): string
    {
        /** @var \Config\AuthGroups */
        $authGroups = config('AuthGroups');

        // Editable data
        $this->data = array_merge($this->data, [
            'title' => lang('Admin.newModel'),
        ]);

        $this->hooks->register(hook('Backend.view:models:new'), function () {
            return render('admin/partials/models_form', [
                'action' => 'new',
                'formAction' => base_url('admin/models'),
            ]);
        });

        return render(
            'admin/models_action',
            array_merge($this->data, [
                // Noneditable data
                'action' => 'new',
                'groups' => $authGroups->groups,
            ])
        );
    }

    public function edit($id)
    {
        /** @var \Config\AuthGroups */
        $authGroups = config('AuthGroups');

        // Editable data
        $this->data = array_merge($this->data, [
            'title' => lang('Admin.editModel'),
        ]);

        $model = service('modelStore')->findArray($id);

        if (empty($model))
            return redirect('admin/models')
                ->with('error', lang('Admin.modelNotFound'));

        $this->hooks->register(hook('Backend.view:models:edit'), function () use ($model) {
            return render('admin/partials/models_form', [
                'action' => 'edit',
                'formAction' => base_url('admin/models/' . $model['id']),
                'model' => $model,
                'isPublic' => service('modelAccess')->isPublic($model['id']),
                'filterableState' => service('modelStore')->filterableState((int) $model['id']),
            ]);
        });

        return render('admin/models_action', array_merge($this->data, [
            // Noneditable data
            'action' => 'edit',
            'model' => $model,
            'groups' => $authGroups->groups,
        ]));
    }

    /**
     * Create a new model.
     *
     * Validates input and delegates creation to the ModelManager.
     * 
     * @return \CodeIgniter\HTTP\Response JSON redirect or error response.
     */
    public function create()
    {
        // Define validation rules.
        $rules = [
            'name' => 'required|min_length[3]|max_length[255]',
            'fields' => 'required',
            'group' => 'permit_empty|min_length[3]|alpha_space',
            'user_groups' => 'required',
            'icon' => ['permit_empty'],
        ];

        // Get only the keys that are in the rules array.
        $data = $this->request->getPost(array_keys($rules));

        // Validate data – using CodeIgniter’s built-in validation.
        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        // Get the validated data.
        $validData = $this->validator->getValidated();
        $userId = auth()->user()->id; // Current authenticated user's ID

        // Dynamic conversion of array inputs
        // Loop through all validated fields. If any field is an array, convert it to JSON.
        foreach ($validData as $key => $value) {
            if (is_array($value)) {
                $validData[$key] = json_encode($value);
            }
        }

        try {
            // Delegate the creation process to the service.
            $modelId = service('modelStore')->create($validData, $userId);
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->respond(
                message: $e->getMessage(),
                success: false
            );
        }

        service('modelAccess')->setPublic((int) $modelId, (bool) $this->request->getPost('public'));

        return redirect('admin/models')
            ->with('success', lang('Admin.modelxSuccessfullyCreated', ['x' => $data['name']]));
    }

    public function update($id)
    {
        $rules = [
            'name' => 'required|min_length[3]|max_length[255]',
            'fields' => 'required',
            'group' => 'permit_empty|min_length[3]|alpha_space',
            'user_groups' => 'required',
            'icon' => 'permit_empty',
        ];

        $data = $this->request->getPost(array_keys($rules));

        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        $validData = $this->validator->getValidated();
        $userId = auth()->user()->id;

        // Dynamic conversion of array inputs
        // Loop through all validated fields. If any field is an array, convert it to JSON.
        foreach ($validData as $key => $value) {
            if (is_array($value)) {
                $validData[$key] = json_encode($value);
            }
        }

        // dd($validData);

        try {
            service('modelStore')->update($id, $validData, $userId);
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->respond($e->getMessage(), withInput: true, success: false);
        }

        service('modelAccess')->setPublic((int) $id, (bool) $this->request->getPost('public'));

        return $this->respond(lang('Admin.modelxSuccessfullySaved', ['x' => $validData['name']]), withInput: false);
    }

    public function delete($id = null)
    {
        $ids = $this->postedIds($id);
        if ($ids === []) {
            return $this->list('bad', lang('Models.noneSelected'));
        }

        try {
            service('modelStore')->trash($ids, (int) auth()->user()->id);
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->list('bad', $e->getMessage());
        }

        return $this->list('ok', lang('Models.trashed'));
    }

    public function purgeDeleted()
    {
        if ((int) service('modelStore')->countTrashed() === 0) {
            return $this->list('bad', lang('Models.trashIsEmpty'));
        }

        try {
            service('modelStore')->emptyTrash();
        } catch (RuntimeException | InvalidArgumentException $e) {
            // Models purged before the failure are gone, and their access settings go with them.
            service('modelAccess')->forgetMissing();

            return $this->list('bad', $e->getMessage());
        }

        service('modelAccess')->forgetMissing();

        return $this->list('ok', lang('Models.emptied'));
    }

    public function restore($id = null)
    {
        $ids = $this->postedIds($id);
        if ($ids === []) {
            return $this->list('bad', lang('Models.noneSelected'));
        }

        try {
            service('modelStore')->restore($ids);
        } catch (RuntimeException | InvalidArgumentException $e) {
            return $this->list('bad', $e->getMessage());
        }

        return $this->list('ok', lang('Models.restored'));
    }

    /**
     * The ids that were posted, or the one in the address, as whole numbers.
     *
     * @return list<int>
     */
    private function postedIds(mixed $id): array
    {
        $posted = $this->request->getPost('ids');
        $ids    = is_array($posted) ? $posted : ($id !== null && $id !== '' ? [$id] : []);

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $n): bool => $n > 0)));
    }

    /**
     * The list as it looks after an action, with a message, under the request the person had.
     */
    private function list(string $type, string $message)
    {
        $data = (new ModelsPage())->build($this->request->getPost() ?: [], $this->perPage());

        if ($this->request->hasHeader('HX-Request')) {
            return $this->response
                ->setHeader('HX-Trigger', json_encode(['hyper:toast' => ['type' => $type, 'message' => $message]], JSON_UNESCAPED_UNICODE))
                ->setBody(view('admin/partials/model_results', $data));
        }

        return redirect()->to($data['urls']['list'])->with($type === 'ok' ? 'success' : 'error', $message);
    }

    private function perPage(): int
    {
        return (int) (service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 25);
    }
}
