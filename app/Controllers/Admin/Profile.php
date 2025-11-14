<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Profile extends AdminController
{
    public function index(): string
    {
        $user = auth()->user();
        $this->data['title'] = $user ? $user->username . "'s " . lang('Admin.profile') : lang('Admin.profile');
        return render('admin/profile', $this->data);
    }

    public function update($hash)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->respond(
                message: lang('Admin.noUserFound'),
                statusCode: 404, // 404 (Not Found)
                withInput: false,
                success: false
            );
        }

        // Check if the provided hash matches the stored hash for the current user
        if (!hash_equals($hash, hash('sha256', auth()->user()->username . auth()->user()->email))) {
            return $this->respond(
                message: lang('Admin.invalidToken'),
                statusCode: 400, // 400 (Bad Request)
                withInput: false,
                success: false
            );
        }

        $id = $user->id;

        $validationRules = [
            'username' => 'required|min_length[3]|max_length[30]',
            'email' => 'required|valid_email',
        ];

        $username = $this->request->getPost('username');
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $userData = [
            'username' => $username,
            'email' => $email,
        ];

        if ($username !== $user->username) {
            $validationRules['username'] = 'required|min_length[3]|max_length[30]|is_unique[users.username]';
        }

        if ($email !== $user->email) {
            $validationRules['email'] = 'required|valid_email|is_unique[auth_identities.secret]';
        }

        if ($password) {
            $validationRules['password'] = 'required|min_length[6]|max_length[255]';
            $userData['password'] = $password;
        }

        if (!$this->validateData($userData, $validationRules)) {
            return $this->respond(
                message: implode(" ", $this->validator->getErrors()),
                withInput: true,
                success: false
            );
        }

        // Get the User Provider (UserModel by default)
        $users = auth()->getProvider();

        $user = $users->findById($id);
        $user->fill($userData);

        try {
            $users->save($user);
        } catch (DatabaseException $e) {
            return $this->respond(
                message: $e->getMessage(),
                statusCode: 500,
                withInput: true,
                success: false,
            );
        }

        return $this->respond(
            message: lang('Admin.profileUpdatedSuccessfully'),
            redirectTo: '/admin/profile',
            withInput: false
        );;
    }
}
