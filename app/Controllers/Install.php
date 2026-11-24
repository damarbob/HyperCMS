<?php

namespace App\Controllers;

use App\Libraries\Install\Installer;
use App\Libraries\Install\InstallState;
use App\Libraries\Install\InstallUrl;
use App\Libraries\Install\Requirements;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The install page, for a host with no shell. It exists only while the site is not installed. Whoever finishes it must be able
 * to read a file in the site's writable folder, and when it is done it answers 404 for good. See `InstallState`.
 */
class Install extends BaseController
{
    /**
     * The session entry that records the install key was typed correctly.
     */
    private const PROVEN = 'install_key_proven';

    private const FIELDS = [
        'db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'db_prefix', 'base_url',
        'admin_username', 'admin_email', 'admin_password', 'admin_password_confirm', 'tick', 'key',
    ];

    public function index(): string|ResponseInterface
    {
        $state = $this->openState();

        return $this->form($state, [
            'db_host' => 'localhost',
            'db_port' => '3306',
            'tick'    => 'cron',
        ], []);
    }

    public function save(): string|RedirectResponse|ResponseInterface
    {
        $state = $this->openState();
        $input = [];
        foreach (self::FIELDS as $field) {
            $input[$field] = (string) $this->request->getPost($field);
        }

        $errors = [];
        if (! $state->keyMatches($input['key'])) {
            $errors['key'] = lang('Install.keyWrong');
        }

        $installer = new Installer($state);
        if ($errors === []) {
            $errors = $installer->validate($input);
        }

        if ($errors !== []) {
            return $this->form($state, $input, $errors);
        }

        $installer->configure($input);

        // The key was typed correctly, and the next two pages are only for the browser that typed it.
        session()->set(self::PROVEN, hash('sha256', $state->key()));

        return redirect()->to(site_url('install/ready'));
    }

    /**
     * The page that asks for the last go-ahead. It does nothing until the button is pressed, which is a POST.
     */
    public function ready(): string|RedirectResponse|ResponseInterface
    {
        $state = $this->openState();
        if ($state->answers() === null || ! $this->proven($state)) {
            return redirect()->to(site_url('install'));
        }

        return view('install/ready');
    }

    public function run(): string|RedirectResponse|ResponseInterface
    {
        $state   = $this->openState();
        $answers = $state->answers();
        if ($answers === null || ! $this->proven($state)) {
            return redirect()->to(site_url('install'));
        }

        $report = (new Installer($state))->run();

        return view('install/done', [
            'report'  => $report,
            'ok'      => $report !== [] && ! in_array(false, array_column($report, 'ok'), true),
            'tickUrl' => is_string($answers['tickUrl'] ?? null) ? $answers['tickUrl'] : null,
            'tick'    => (string) ($answers['tick'] ?? 'later'),
            'spark'   => ROOTPATH . 'spark',
        ]);
    }

    /**
     * Whether this browser typed the install key. The saved answers and the tick address must not go to anyone else.
     */
    private function proven(InstallState $state): bool
    {
        return hash_equals(hash('sha256', $state->key()), (string) session(self::PROVEN));
    }

    /**
     * The state of the site, or a 404 when the page must not exist. No page leaks the reason.
     */
    private function openState(): InstallState
    {
        $state = InstallState::current();
        if (! $state->open()) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $state;
    }

    /**
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function form(InstallState $state, array $input, array $errors): string
    {
        $checks = (new Requirements())->check();
        // The address of this request is the best first guess for the site's own.
        $input['base_url'] = trim($input['base_url'] ?? '') !== '' ? $input['base_url'] : InstallUrl::current()['baseURL'];
        $state->key();

        return view('install/form', [
            'input'    => $input,
            'errors'   => $errors,
            'checks'   => $checks,
            'ready'    => Requirements::passed($checks),
            'keyPath'  => 'writable/' . InstallState::KEY,
        ]);
    }

}
