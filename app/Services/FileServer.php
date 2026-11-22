<?php

namespace App\Services;

use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Mimes;
use CodeIgniter\Files\File;

class FileServer
{
    public function __construct()
    {
        /**
         * Helpers to be loaded.
         *
         * The 'hyper_hex' helper is required for hex_encode and hex_decode functions.
         * The 'hyper_directory' helper is required for directory path validation.
         */
        // Ensure that the required helpers are loaded.
        helper(['hyper_hex', 'hyper_directory']);
    }

    /**
     * Serves a file based on an encoded path.
     *
     * The path is read only from the public folder (`FCPATH`), never from the project root,
     * so nothing outside it can be requested. Dot files and files with an executable or server
     * config extension are refused, whatever the folder.
     *
     * A path that begins with `public/` is also tried without that part, for links made when the
     * file manager root was the project root.
     *
     * @param string $encodedPath The hex-encoded and URL-encoded path.
     * @return array An associative array with keys 'content_type' and 'body'.
     * @throws PageNotFoundException if the file cannot be found or may not be served.
     */
    public function serve(string $encodedPath): array
    {
        // Decode the encoded path.
        $path = urldecode(hex_decode($encodedPath));

        $fullPath = $this->resolve($path);
        if ($fullPath === null) {
            // Extract the base file name (including extension) from the decoded path.
            $fileName = pathinfo($path, PATHINFO_BASENAME);
            throw PageNotFoundException::forPageNotFound(lang('Admin.filexNotFound', ['x' => $fileName]));
        }

        // Use CodeIgniter's File class to get file details.
        $file     = new File($fullPath);
        $mimeType = Mimes::guessTypeFromExtension($file->getExtension());

        // Return an array with the file's contents and MIME type.
        return [
            'content_type' => $mimeType,
            'body'         => file_get_contents($fullPath)
        ];
    }

    /**
     * Find the file a decoded path names inside the public folder, or null when it is missing or may not be served.
     */
    private function resolve(string $path): ?string
    {
        if ($path === '' || strpos($path, "\0") !== false) {
            return null;
        }

        $root = realpath(FCPATH);
        if ($root === false) {
            return null;
        }

        foreach ([$path, self::replacePublicFolder($path, '')] as $candidate) {
            $full = realpath($root . DIRECTORY_SEPARATOR . ltrim($candidate, '/\\'));
            if ($full === false || !is_file($full) || !validate_directory($full, $root)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($full, strlen($root)));
            if (is_safe_archive_entry(ltrim($relative, '/'))) {
                return $full;
            }
        }

        return null;
    }

    /**
     * Replace the "public" folder with the provided folder name in the path.
     *
     * This is useful when your production environment uses a public_html folder.
     *
     * @param string $path The original path.
     * @param string $folderName The replacement folder name. (e.g., empty string or "public_html")
     * @return string The updated path.
     */
    private static function replacePublicFolder(string $path, string $folderName): string
    {
        // Normalize directory separators for cross-platform consistency.
        $normalizedPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

        // Split the path into parts.
        $pathComponents = explode(DIRECTORY_SEPARATOR, $normalizedPath);

        // If the first component is "public", replace it.
        if (isset($pathComponents[0]) && $pathComponents[0] === 'public') {
            $pathComponents[0] = $folderName;
        }

        return implode(DIRECTORY_SEPARATOR, $pathComponents);
    }
}
