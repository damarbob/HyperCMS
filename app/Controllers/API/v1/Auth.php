<?php

namespace App\Controllers\API\v1;

use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\RESTful\ResourceController;

class Auth extends ResourceController
{
    public function login()
    {
        // 1. Get the credentials
        // Read a JSON body when the request says it is JSON, otherwise read the form fields.
        // Never read the query string, so credentials cannot end up in a URL.
        try {
            $input = $this->request->is('json') ? $this->request->getJSON(true) : $this->request->getPost();
        } catch (HTTPException $e) {
            return $this->fail('The request body is not valid JSON.');
        }

        $input = is_array($input) ? $input : [];

        // Anything that is not a string is treated as missing, so the validation below rejects it.
        $email    = is_string($input['email'] ?? null) ? $input['email'] : null;
        $password = is_string($input['password'] ?? null) ? $input['password'] : null;

        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        // Validate strictly using the inputs we just fetched, to ensure we validate what we use
        if (! $this->validateData(['email' => $email, 'password' => $password], $rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // 2. Retrieve the User (without logging them in via Session)
        /** @var \CodeIgniter\Shield\Models\UserModel */
        $users = auth()->getProvider(); // Get the UserModel
        $user  = $users->findByCredentials(['email' => $email]);

        // 3. Verify the Password Manually (Mitigate Timing Attacks)
        // If the user is not found, we still verify against a dummy hash so the
        // response takes as long as a wrong password does.
        // The dummy hash must be a valid bcrypt hash at the same cost as `Auth::$hashCost` (12).
        $dummyHash = '$2y$12$MbvqpZVmk4OEXK68Vx//ZOr1DATsczDBge9ocIZ3qJM8i.HV4rlH2';

        $pHash = ($user) ? $user->password_hash : $dummyHash;
        $check = service('passwords')->verify($password, $pHash);

        if (! $user || ! $check) {
            return $this->failUnauthorized('Invalid login credentials');
        }

        // 4. Generate the Access Token
        // The token may only read entries. Add a scope here when the API grows.
        $token = $user->generateAccessToken('api-login', ['entries.read']);

        // 5. Return the Raw Token
        return $this->respond([
            'status' => 200,
            'access_token'  => $token->raw_token, // IMPORTANT: accessible only once here
            'user'   => [
                'id'       => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
            ]
        ]);
    }
}
