<?php

namespace UserManagement\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Shield\Entities\User;

class Users extends BaseController
{
    public function index()
    {
        /** @var \Config\AuthGroups */
        $authGroups = config('AuthGroups');

        $this->data['title'] = lang('UserManagement.moduleName');

        return render('\UserManagement\Views\users', array_merge($this->data, [
            'groups' => $authGroups->groups
        ]));
    }

    public function show($id)
    {
        $db = db_connect();
        $user = $db->table('users')
            ->select('users.id, username, secret as email, COALESCE(GROUP_CONCAT(auth_groups_users.group SEPARATOR ","), "") as groups')
            ->where('users.id', $id)
            ->join('auth_identities', 'auth_identities.user_id = users.id', 'left')
            ->join('auth_groups_users', 'auth_groups_users.user_id = users.id', 'left')
            ->groupBy('users.id')
            ->get()
            ->getRowArray();

        return $this->response->setJSON($user);
    }

    public function getUsers()
    {
        // Retrieve DataTables GET parameters.
        $data    = $this->request->getGet();
        $draw    = isset($data['draw']) ? intval($data['draw']) : 1;
        $start   = isset($data['start']) ? intval($data['start']) : 0;
        $length  = isset($data['length']) ? intval($data['length']) : -1;
        $search  = isset($data['search']['value']) ? $data['search']['value'] : '';
        $order   = $data['order'] ?? [];
        $columns = $data['columns'] ?? [];

        $db = db_connect();

        // Build a base query.
        $builder = $db->table('users')
            ->select('users.id, username, secret as email, COALESCE(GROUP_CONCAT(auth_groups_users.group SEPARATOR ","), "") as groups')
            ->join('auth_identities', 'auth_identities.user_id = users.id', 'left')
            ->join('auth_groups_users', 'auth_groups_users.user_id = users.id', 'left')
            ->groupBy('users.id');

        // Clone the base query for total records count (without filtering).
        $totalData = $builder->countAllResults(false);

        // Apply search filtering if a search term is provided.
        if (!empty($search)) {
            // Searching on 'username' and 'secret' fields.
            $builder->groupStart()
                ->like('username', $search)
                ->orLike('secret', $search)
                ->orLike('group', $search)
                ->groupEnd();
        }

        // Clone for filtered count.
        $recordsFiltered = $builder->countAllResults(false);

        // Apply ordering.
        if (!empty($order) && !empty($columns)) {
            // DataTables sends order[0]['column'] as the index of the column to order by.
            $orderColumnIndex = $order[0]['column'];
            $orderDir         = $order[0]['dir'];
            // Get the column name from the columns definition.
            $orderField = isset($columns[$orderColumnIndex]['data']) ? $columns[$orderColumnIndex]['data'] : 'users.id';
            $builder->orderBy($orderField, $orderDir);
        }

        // Apply LIMIT and OFFSET if needed.
        if ($length != -1) {
            $builder->limit($length, $start);
        }

        // Get the final result.
        $users = $builder->get()->getResultArray();

        // Prepare and return the JSON response as required by DataTables.
        $response = [
            "draw"            => $draw,
            "recordsTotal"    => $totalData,
            "recordsFiltered" => $recordsFiltered,
            "data"            => $users
        ];

        return $this->response->setJSON($response);
    }

    public function save()
    {
        $userId = $this->request->getPost('id');
        $data = [
            'username' => $this->request->getPost('username'),
            'email'    => $this->request->getPost('email'),
        ];

        // Password handling
        if (!empty($this->request->getPost('password'))) {
            $data['password'] = $this->request->getPost('password');
        }

        $groups = $this->request->getPost('groups') ?? [];

        $users = auth()->getProvider();
        $user = $userId ? $users->findById($userId) : new User($data);

        if ($userId) {
            $user->fill($data);
        }

        try {
            $users->save($user);

            // For new users, refresh the user instance to get the auto-generated ID
            if (!$userId) {
                $user = $users->findById($users->getInsertID());
            }

            // Sync groups
            $user->syncGroups(...$groups);

            return $this->response->setJSON([
                'success' => true,
                'message' => lang('UserManagement.userSavedSuccessfully')
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function delete($id)
    {
        try {
            $user = auth()->getProvider()->findById($id);
            auth()->getProvider()->delete($user->id, true);

            return $this->response->setJSON([
                'success' => true,
                'message' => lang('UserManagement.userDeletedSuccessfully')
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
