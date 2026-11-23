<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Fields\FieldTypes;
use App\Filters\ApiAuthFilter;
use CodeIgniter\Shield\Models\UserIdentityModel;

/**
 * The access tokens of the signed-in person: the list, a form to make one, and a button to revoke one or all. A token is
 * shown once, right after it is made, because only its hash is kept.
 */
class Tokens extends AdminController
{
    /**
     * The scopes a token can have. Each is a part of the API.
     */
    private const SCOPES = ['entries.read', 'models.read'];

    public function index()
    {
        return $this->page(['name' => '', 'scopes' => self::SCOPES], '', 200);
    }

    public function create()
    {
        $name   = trim((string) $this->request->getPost('name'));
        $asked  = $this->request->getPost('scopes');
        $scopes = array_values(array_intersect(self::SCOPES, is_array($asked) ? array_map('strval', $asked) : []));

        $problem = '';
        if ($name === '' || mb_strlen($name) > 100) {
            $problem = lang('Tokens.nameInvalid');
        } elseif ($scopes === []) {
            $problem = lang('Tokens.scopesRequired');
        }
        if ($problem !== '') {
            return $this->page(['name' => $name, 'scopes' => $scopes], $problem, 422);
        }

        $token = ApiAuthFilter::issueToken(auth()->user(), $name, $scopes);

        // The raw token is in this one flash and nowhere else.
        session()->setFlashdata('new_token', ['name' => $name, 'token' => $token->raw_token, 'expires' => $token->expires->format('Y-m-d H:i:s')]);

        return redirect()->to(base_url('admin/tokens'));
    }

    public function revoke($id)
    {
        $user = auth()->user();
        $row  = model(UserIdentityModel::class)->where('id', (int) $id)->where('user_id', $user->id)->where('type', 'access_token')->first();

        if ($row === null) {
            return redirect()->to(base_url('admin/tokens'))->with('error', lang('Tokens.notFound'));
        }

        model(UserIdentityModel::class)->delete((int) $id);

        return redirect()->to(base_url('admin/tokens'))->with('success', lang('Tokens.revoked'));
    }

    public function revokeAll()
    {
        auth()->user()->revokeAllAccessTokens();

        return redirect()->to(base_url('admin/tokens'))->with('success', lang('Tokens.allRevoked'));
    }

    /**
     * @param array{name: string, scopes: list<string>} $values
     */
    private function page(array $values, string $error, int $status)
    {
        $types  = FieldTypes::core();
        $when   = static fn (?string $value): string => $value === null || $value === '' ? '<span class="muted">' . esc(lang('Tokens.never')) . '</span>' : $types->get('datetime')->cell($value, ['id' => 'x', 'type' => 'datetime']);
        $tokens = [];
        foreach (auth()->user()->accessTokens() as $token) {
            $tokens[] = [
                'id'      => (int) $token->id,
                'label'   => (string) $token->name,
                'cells'   => [
                    'name'    => '<strong>' . esc((string) $token->name) . '</strong>',
                    'scopes'  => '<div class="chips">' . implode('', array_map(static fn (string $s): string => '<span class="chip">' . esc($s) . '</span>', (array) $token->scopes)) . '</div>',
                    'used'    => $when($token->last_used_at ? (string) $token->last_used_at : null),
                    'expires' => $when($token->expires ? (string) $token->expires : null),
                ],
                'actions' => '<form method="post" action="' . attr(base_url('admin/tokens/' . (int) $token->id . '/revoke')) . '">' . csrf_field() . ui('button', ['label' => lang('Tokens.revoke'), 'type' => 'submit', 'variant' => 'quiet', 'size' => 'small']) . '</form>',
            ];
        }

        $this->data = array_merge($this->data, [
            'title'    => lang('Tokens.title'),
            'crumbs'   => [[lang('Tokens.title'), null]],
            'columns'  => [
                ['key' => 'name', 'label' => lang('Tokens.name'), 'class' => 'label-cell'],
                ['key' => 'scopes', 'label' => lang('Tokens.scopes')],
                ['key' => 'used', 'label' => lang('Tokens.lastUsed')],
                ['key' => 'expires', 'label' => lang('Tokens.expires')],
            ],
            'rows'     => $tokens,
            'values'   => $values,
            'error'    => $error,
            'scopes'   => self::SCOPES,
            'newToken' => session()->getFlashdata('new_token'),
        ]);

        return $this->response->setStatusCode($status)->setBody(view('admin/tokens', $this->data));
    }
}
