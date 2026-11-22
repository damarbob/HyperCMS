<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Saves the colour scheme the signed-in user chose: `system`, `light` or `dark`.
 *
 * The choice is kept in the user's own setting, so it follows them to another device, and in a cookie, so the sign in
 * page can use it before anyone is signed in.
 */
class Theme extends BaseController
{
    public const CHOICES = ['system', 'light', 'dark'];

    public function save(): ResponseInterface
    {
        $choice = (string) $this->request->getPost('theme');
        if (! in_array($choice, self::CHOICES, true)) {
            return $this->response->setStatusCode(422)->setJSON(['message' => lang('Ui.requestFailed')]);
        }

        $user = auth()->user();
        if ($user !== null) {
            setting()->set('Hyper.theme', $choice, 'user:' . $user->id);
        }

        return $this->response
            ->setCookie([
                'name'     => 'hyper_theme',
                'value'    => $choice,
                'expire'   => 31536000,
                'path'     => '/',
                'samesite' => 'Lax',
                'httponly' => true,
            ])
            ->setStatusCode(204);
    }
}
