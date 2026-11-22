<?php
// hyper_directory_helper.php

if (!function_exists('validate_directory')) {
    /**
     * Validate that a given directory path is located within a specified base directory.
     *
     * This function normalizes both paths by removing any trailing slashes (forward or backward)
     * and then checks if the provided directory path begins with the base directory.
     *
     * Note: This method does not consider the special case where the directory is exactly the same
     * as the base directory.
     *
     * @param string $directory The directory path to validate.
     * @param string $baseDir   The base directory that $directory should start with.
     * @return bool True if the directory is within the base directory, false otherwise.
     */
    function validate_directory(string $directory, string $baseDir): bool
    {
        $normalizedBaseDir      = rtrim($baseDir, '/\\');
        $normalizedRelativePath = rtrim($directory, '/\\');

        if (!$normalizedRelativePath) {
            return false;
        }

        // The base must end at a separator, so a sibling such as `public_old` is not taken for a child of `public`.
        return $normalizedRelativePath === $normalizedBaseDir
            || strpos($normalizedRelativePath, $normalizedBaseDir . DIRECTORY_SEPARATOR) === 0
            || strpos($normalizedRelativePath, $normalizedBaseDir . '/') === 0;
    }
}

if (!function_exists('validate_directory_within_base')) {
    /**
     * Validate that a given directory is the same as or a subdirectory of the specified base directory.
     *
     * Both paths are normalized before the comparison. For example, if $baseDir is
     * "/var/www/files", then $directory can be "/var/www/files" or "/var/www/files/subfolder".
     *
     * @param string $directory The directory path to validate.
     * @param string $baseDir   The base directory for validation.
     * @return bool True if the directory is equal to or within the base directory, false otherwise.
     */
    function validate_directory_within_base(string $directory, string $baseDir): bool
    {
        $normalizedBaseDir  = rtrim($baseDir, '/\\');
        $normalizedDirectory = rtrim($directory, '/\\');

        return $normalizedDirectory === $normalizedBaseDir
            || strpos($normalizedDirectory, $normalizedBaseDir . DIRECTORY_SEPARATOR) === 0
            || strpos($normalizedDirectory, $normalizedBaseDir . '/') === 0;
    }
}

if (!function_exists('is_safe_file_name')) {
    /**
     * Tell whether a file name may be created, uploaded, renamed to or unpacked.
     *
     * A name is refused when it:
     *  - is empty, or holds a path separator, a colon, a null byte or a control character,
     *  - starts with a dot, which covers `.htaccess`, `.env` and `.user.ini`,
     *  - ends with a dot or a space, which Windows drops, so `x.php.` would become `x.php`,
     *  - holds an executable or server config extension anywhere after its first part,
     *    because some Apache setups run `x.php.jpg`.
     *
     * @param string $name A file name only, with no folder.
     */
    function is_safe_file_name(string $name): bool
    {
        if ($name === '' || preg_match('/[\/\:\x00-\x1f]/', $name) === 1) {
            return false;
        }

        if ($name[0] === '.' || preg_match('/[. ]$/', $name) === 1) {
            return false;
        }

        $parts = explode('.', strtolower($name));
        array_shift($parts); // The part before the first dot is the base name, not an extension.
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^(php[0-9]?|phtml|phar|pht|phps|shtml|cgi|htaccess|htpasswd)$/', $part) === 1) {
                return false;
            }
        }

        return !str_ends_with(strtolower($name), '.user.ini');
    }
}

if (!function_exists('is_safe_archive_entry')) {
    /**
     * Tell whether an entry of an archive may be unpacked: a relative path with no `..` part,
     * where every part is a safe file name. A folder entry ends with a slash and is checked the same way.
     */
    function is_safe_archive_entry(string $entry): bool
    {
        $entry = str_replace('\\', '/', $entry);
        if ($entry === '' || $entry[0] === '/' || strpos($entry, "\0") !== false || preg_match('#^[A-Za-z]:#', $entry) === 1) {
            return false;
        }

        foreach (explode('/', rtrim($entry, '/')) as $part) {
            if ($part === '..' || !is_safe_file_name($part)) {
                return false;
            }
        }

        return true;
    }
}
