<?php

/**
 * Decides which tracked files go into the release package. A site needs the application, the modules,
 * the public folder and the writable folder skeleton. It does not need tests, Docker files, build
 * tooling or the notes of the people who build it.
 */
final class PackageRules
{
    /**
     * Folders that stay out, matched on the start of the path.
     */
    private const EXCLUDED_FOLDERS = [
        '.agent/',
        '.docker/',
        '.github/',
        'tests/',
        'tools/',
    ];

    /**
     * Single files that stay out.
     */
    private const EXCLUDED_FILES = [
        '.dockerignore',
        '.gitattributes',
        '.gitignore',
        'CLAUDE.md',
        'Dockerfile',
        'builds',
        'docker-compose.yml',
        'init.sql',
        'package-lock.json',
        'package.json',
        'phpunit.xml.dist',
    ];

    /**
     * Folders that must exist and be writable on the host. The package carries an index.html in each,
     * and the web server must never list or serve what the application writes there.
     */
    public const WRITABLE_FOLDERS = [
        'writable/cache',
        'writable/logs',
        'writable/session',
        'writable/uploads',
    ];

    public static function includes(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '') {
            return false;
        }

        // A settings file holds secrets, whatever it is called. The template `env` has no dot and stays.
        if (str_starts_with($path, '.env')) {
            return false;
        }

        foreach (self::EXCLUDED_FOLDERS as $folder) {
            if (str_starts_with($path, $folder)) {
                return false;
            }
        }

        return ! in_array($path, self::EXCLUDED_FILES, true);
    }
}
