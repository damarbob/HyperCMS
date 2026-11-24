<?php

namespace App\Libraries\Install;

/**
 * What the host must offer before HyperCMS can be installed, as a list the install page shows. Each check names itself by a
 * language key, so the page can show it in the language of the visitor.
 */
final class Requirements
{
    public const MIN_PHP = '8.1.0';

    /**
     * Extensions the framework and StarDust cannot run without.
     */
    private const EXTENSIONS = ['intl', 'mbstring', 'json', 'mysqli', 'pdo_mysql'];

    /**
     * Folders the site writes to while it runs, relative to the project folder.
     */
    private const WRITABLE = ['writable', 'writable/cache', 'writable/logs', 'writable/session', 'writable/uploads'];

    public function __construct(private readonly string $root = ROOTPATH)
    {
    }

    /**
     * @return list<array{key: string, ok: bool, detail: string}>
     */
    public function check(): array
    {
        $checks   = [];
        $checks[] = [
            'key'    => 'php',
            'ok'     => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'detail' => PHP_VERSION . ' (needs ' . self::MIN_PHP . ' or newer)',
        ];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = ['key' => 'extension', 'ok' => extension_loaded($extension), 'detail' => $extension];
        }

        $checks[] = [
            'key'    => 'packages',
            'ok'     => is_file($this->root . '/vendor/autoload.php') && is_dir($this->root . '/public/assets/vendor/htmx.org'),
            'detail' => 'vendor/ and public/assets/vendor/',
        ];

        foreach (self::WRITABLE as $folder) {
            $checks[] = ['key' => 'writable', 'ok' => is_dir($this->root . '/' . $folder) && is_writable($this->root . '/' . $folder), 'detail' => $folder . '/'];
        }

        // The settings file is made by the install, so the project folder must allow a new file, or the existing file a change.
        $env      = $this->root . '/.env';
        $checks[] = [
            'key'    => 'envfile',
            'ok'     => is_file($env) ? is_writable($env) : is_writable($this->root),
            'detail' => '.env',
        ];

        return $checks;
    }

    /**
     * @param list<array{key: string, ok: bool, detail: string}> $checks
     */
    public static function passed(array $checks): bool
    {
        foreach ($checks as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }
}
