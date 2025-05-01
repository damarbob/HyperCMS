<?php

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

        if (!$normalizedRelativePath || strpos($normalizedRelativePath, $normalizedBaseDir) !== 0) {
            return false;
        }

        return true;
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

        return $normalizedDirectory === $normalizedBaseDir ||
            strpos($normalizedDirectory, $normalizedBaseDir) === 0;
    }
}
