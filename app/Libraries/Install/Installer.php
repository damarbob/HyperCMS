<?php

namespace App\Libraries\Install;

use App\Libraries\Upgrade\CounterSchemaStore;
use App\Libraries\Upgrade\Steps\Steps;
use CodeIgniter\Shield\Entities\User;
use PDO;
use PDOException;
use StarDust\Exception\UnsupportedServerException;
use Throwable;

/**
 * What the install page does, in two parts. `validate()` and `configure()` check the answers, try the database and write the
 * `.env` file. `run()` then makes the tables, StarDust's tables and the first superadmin, with the settings that file holds, and
 * locks the page. They are two requests because CodeIgniter reads `.env` once at the start of a request, so the database
 * settings only take effect on the next one. `run()` can be repeated after a failure, since the migrations skip what is done.
 */
final class Installer
{
    public const TICK_METHODS = ['cron', 'url', 'later'];

    public function __construct(private readonly InstallState $state, private readonly string $root = ROOTPATH)
    {
    }

    /**
     * Checks the answers, and reaches out to the database. Returns the errors by field name, empty when all is well.
     *
     * @param array<string, string> $input
     *
     * @return array<string, string>
     */
    public function validate(array $input): array
    {
        $errors = [];

        foreach (['db_host' => 'dbHost', 'db_name' => 'dbName', 'db_user' => 'dbUser', 'admin_username' => 'adminUsername', 'admin_email' => 'adminEmail'] as $field => $key) {
            if (trim($input[$field] ?? '') === '') {
                $errors[$field] = lang('Install.required');
            }
        }

        $port = $input['db_port'] ?? '';
        if (filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            $errors['db_port'] = lang('Install.portInvalid');
        }

        $prefix = $input['db_prefix'] ?? '';
        if ($prefix !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]{0,19}$/', $prefix) !== 1) {
            $errors['db_prefix'] = lang('Install.prefixInvalid');
        }

        foreach (['db_host', 'db_name', 'db_user', 'db_password'] as $field) {
            if (! EnvFile::writable($input[$field] ?? '')) {
                $errors[$field] ??= lang('Install.valueUnwritable');
            }
        }

        $base = $this->baseUrl($input['base_url'] ?? '');
        if ($base === null) {
            $errors['base_url'] = lang('Install.baseUrlInvalid');
        }

        $username = trim($input['admin_username'] ?? '');
        if ($username !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{2,29}$/', $username) !== 1) {
            $errors['admin_username'] = lang('Install.usernameInvalid');
        }

        $email = trim($input['admin_email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['admin_email'] = lang('Install.emailInvalid');
        }

        $password = $input['admin_password'] ?? '';
        if (($errors['admin_username'] ?? $errors['admin_email'] ?? null) === null) {
            $check = service('passwords')->check($password, new User(['username' => $username, 'email' => $email]));
            if (! $check->isOk()) {
                $errors['admin_password'] = $check->reason();
            }
        }
        if ($password !== ($input['admin_password_confirm'] ?? '')) {
            $errors['admin_password_confirm'] = lang('Install.passwordMismatch');
        }

        if (! in_array($input['tick'] ?? '', self::TICK_METHODS, true)) {
            $errors['tick'] = lang('Install.required');
        }

        if ($errors === []) {
            $problem = $this->databaseProblem($input);
            if ($problem !== null) {
                $errors['db_host'] = $problem;
            }
        }

        return $errors;
    }

    /**
     * Writes `.env` and the saved answers. Call it only after `validate()` found nothing wrong.
     *
     * @param array<string, string> $input
     *
     * @return array{tickUrl: string|null} What the done page needs to show.
     */
    public function configure(array $input): array
    {
        $base       = (string) $this->baseUrl($input['base_url']);
        $tickSecret = $input['tick'] === 'url' ? bin2hex(random_bytes(24)) : '';

        $values = [
            'CI_ENVIRONMENT'             => 'production',
            'app.baseURL'                => $base,
            'database.default.hostname'  => trim($input['db_host']),
            'database.default.database'  => trim($input['db_name']),
            'database.default.username'  => trim($input['db_user']),
            'database.default.password'  => $input['db_password'] ?? '',
            'database.default.DBDriver'  => 'MySQLi',
            'database.default.DBPrefix'  => $input['db_prefix'] ?? '',
            'database.default.port'      => (string) (int) $input['db_port'],
            'encryption.key'             => 'hex2bin:' . bin2hex(random_bytes(32)),
            'auth.allowRegistration'     => 'false',
        ];
        if ($tickSecret !== '') {
            $values['hyperengine.tickSecret'] = $tickSecret;
        }

        $this->writeEnv(EnvFile::render($values, [
            'Written by the HyperCMS install page. Change a setting here, then reload the site.',
            'The database password and the encryption key are secrets. Never put this file in the public folder.',
        ]));

        $this->state->saveAnswers([
            'admin' => [
                'username'      => trim($input['admin_username']),
                'email'         => trim($input['admin_email']),
                'password_hash' => service('passwords')->hash($input['admin_password']),
            ],
            'tick'    => $input['tick'],
            'tickUrl' => $tickSecret === '' ? null : $base . 'hyper/tick?key=' . $tickSecret,
        ]);

        return ['tickUrl' => $tickSecret === '' ? null : $base . 'hyper/tick?key=' . $tickSecret];
    }

    /**
     * Makes the tables and the first superadmin, then locks the page. Stops at the first step that fails.
     *
     * @return list<array{step: string, ok: bool, detail: string}>
     */
    public function run(): array
    {
        $answers = $this->state->answers();
        if ($answers === null) {
            return [['step' => 'answers', 'ok' => false, 'detail' => lang('Install.noAnswers')]];
        }

        @set_time_limit(0);
        $report = [];

        $report[] = $this->step('migrations', static function (): string {
            $runner = service('migrations');
            $runner->setNamespace(null);
            if (! $runner->latest()) {
                throw new \RuntimeException(lang('Install.migrationsFailed') . ' ' . implode(' ', $runner->getCliMessages()));
            }

            return '';
        });
        if (! $report[0]['ok']) {
            return $report;
        }

        $report[] = $this->step('engine', static function (): string {
            try {
                service('stardust')->bootstrap();
            } catch (UnsupportedServerException $e) {
                throw new \RuntimeException(lang('Install.serverTooOld') . ' ' . $e->getMessage(), 0, $e);
            }
            // A new install is made in the latest shape, so it has no conversion to run.
            (new CounterSchemaStore(db_connect()))->setVersion(Steps::LATEST);

            return '';
        });
        if (! $report[1]['ok']) {
            return $report;
        }

        $report[] = $this->step('admin', function () use ($answers): string {
            $this->createSuperadmin($answers['admin']);

            return (string) $answers['admin']['username'];
        });
        if (! $report[2]['ok']) {
            return $report;
        }

        $this->state->finish();

        return $report;
    }

    /**
     * The address of the site with a closing slash, or null when it is not a web address.
     */
    public function baseUrl(string $given): ?string
    {
        $given = trim($given);
        if (preg_match('#^https?://[^\s/?\#]+(/[^\s?\#]*)?$#i', $given) !== 1) {
            return null;
        }

        return rtrim($given, '/') . '/';
    }

    /**
     * Opens the database with the answers, before anything is written. Returns what is wrong, or null when it can be used.
     *
     * @param array<string, string> $input
     */
    private function databaseProblem(array $input): ?string
    {
        try {
            $pdo = $this->connect($input);
        } catch (PDOException $e) {
            return lang('Install.databaseUnreachable', ['message' => $e->getMessage()]);
        }

        $reported = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        if (! ServerVersion::supported($reported)) {
            return lang('Install.serverTooOld') . ' (' . ServerVersion::label($reported) . ')';
        }

        $prefix = $input['db_prefix'] ?? '';
        if ($prefix === '' && ($this->tableExists($pdo, 'models') || $this->tableExists($pdo, 'model_data'))) {
            return lang('Install.oldLayout');
        }

        if ($this->tableExists($pdo, $prefix . 'hyper_models') && $this->hasSuperadmin($pdo, $prefix)) {
            return lang('Install.alreadyInstalled');
        }

        return null;
    }

    /**
     * @param array<string, string> $input
     */
    private function connect(array $input): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', trim($input['db_host']), (int) $input['db_port'], trim($input['db_name'])),
            trim($input['db_user']),
            $input['db_password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8],
        );
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function hasSuperadmin(PDO $pdo, string $prefix): bool
    {
        if (! $this->tableExists($pdo, $prefix . 'auth_groups_users')) {
            return false;
        }

        // The table name is the validated prefix plus a fixed word, so it is safe to put in the statement.
        $statement = $pdo->prepare('SELECT COUNT(*) FROM `' . $prefix . 'auth_groups_users` WHERE `group` = ?');
        $statement->execute(['superadmin']);

        return (int) $statement->fetchColumn() > 0;
    }

    private function writeEnv(string $content): void
    {
        $path = $this->root . '/.env';

        // The files hold the database password, so they are made readable by the owner only from the first byte.
        $previous = umask(0o077);

        try {
            if (is_file($path)) {
                $backup = $path . '.bak';
                if (@copy($path, $backup)) {
                    @chmod($backup, 0o600);
                }
            }

            $temporary = $path . '.new';
            if (file_put_contents($temporary, $content, LOCK_EX) === false) {
                throw new \RuntimeException(lang('Install.envNotWritten'));
            }
            @chmod($temporary, 0o640);

            if (! @rename($temporary, $path)) {
                @unlink($temporary);

                throw new \RuntimeException(lang('Install.envNotWritten'));
            }
        } finally {
            umask($previous);
        }
    }

    /**
     * @param array{username: string, email: string, password_hash: string} $admin
     */
    private function createSuperadmin(array $admin): void
    {
        $users = auth()->getProvider();
        $found = $users->findByCredentials(['email' => $admin['email']]) ?? $users->where('username', $admin['username'])->first();

        if ($found === null) {
            $new = new User(['username' => $admin['username'], 'email' => $admin['email']]);
            $new->setPasswordHash($admin['password_hash']);
            $users->save($new);
            $found = $users->findById($users->getInsertID());
        }

        $found->syncGroups('superadmin');
        $found->activate();
    }

    /**
     * @param callable(): string $work
     *
     * @return array{step: string, ok: bool, detail: string}
     */
    private function step(string $name, callable $work): array
    {
        try {
            return ['step' => $name, 'ok' => true, 'detail' => $work()];
        } catch (Throwable $e) {
            log_message('error', 'Install step ' . $name . ' failed. ' . $e->getMessage());

            return ['step' => $name, 'ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
