<?php

namespace App\Libraries\Install;

/**
 * Works out the address of the site from the request itself. Before the install there is no `app.baseURL` in the settings, so
 * CodeIgniter would build every link and redirect for `http://localhost:8080/`, which a real host answers with nothing.
 */
final class InstallUrl
{
    /**
     * @param string $scheme      `http` or `https`.
     * @param string $authority   The host, with the port when it is not the usual one.
     * @param string $scriptName  The path of the front controller, such as `/hyper/public/index.php`.
     * @param string $requestPath The path that was asked for, such as `/hyper/public/install`.
     *
     * @return array{baseURL: string, indexPage: string}
     */
    public static function build(string $scheme, string $authority, string $scriptName, string $requestPath): array
    {
        $folder = str_replace('\\', '/', dirname($scriptName));
        $folder = $folder === '/' || $folder === '.' ? '' : rtrim($folder, '/');

        // A host without URL rewriting is asked as `/index.php/install`, and must keep getting `index.php` in its links.
        $viaIndex = str_contains($requestPath, '/' . basename($scriptName) . '/') || str_ends_with($requestPath, '/' . basename($scriptName));

        return [
            'baseURL'   => $scheme . '://' . $authority . $folder . '/',
            'indexPage' => $viaIndex ? basename($scriptName) : '',
        ];
    }

    /**
     * The same, for the request in progress.
     *
     * @return array{baseURL: string, indexPage: string}
     */
    public static function current(): array
    {
        // CodeIgniter builds the request's own address from the settings, which do not exist yet, so the server variables are read.
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$|^\[[0-9A-Fa-f:.]+\](:\d{1,5})?$/', $host) !== 1) {
            $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
        }

        $secure = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return self::build($secure ? 'https' : 'http', $host, (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'), $path);
    }
}
