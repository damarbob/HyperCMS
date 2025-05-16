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
     * It decodes the provided path, finds a valid file in your development and production
     * directories (or within a public_html structure), and returns the file content
     * along with its MIME type.
     *
     * @param string $encodedPath The hex-encoded and URL-encoded path.
     * @return array An associative array with keys 'content_type' and 'body'.
     * @throws PageNotFoundException if the file cannot be found.
     */
    public function serve(string $encodedPath): array
    {
        // Decode the encoded path.
        $path = urldecode(hex_decode($encodedPath));

        // Determine possible full paths in different environments.
        $developmentFullPath = realpath(ROOTPATH . '/' . $path);
        $productionFullPath  = realpath(FCPATH . '/' . $path);
        $productionPublicHtmlFullPath = realpath(FCPATH . '/' . self::replacePublicFolder($path, ""));

        // Validate that at least one of these paths exists and is within the allowed directories.
        if (
            (
                !validate_directory($developmentFullPath, ROOTPATH) || !file_exists($developmentFullPath)
            ) && (
                !validate_directory($productionFullPath, FCPATH) || !file_exists($productionFullPath)
            ) && (
                !validate_directory_within_base($productionPublicHtmlFullPath, FCPATH) || !file_exists($productionPublicHtmlFullPath)
            )
        ) {
            throw PageNotFoundException::forPageNotFound(lang('Admin.fileNotFound'));
        }

        // Choose a valid path—development takes precedence.
        $fullPath = $developmentFullPath ?: ($productionFullPath ?: $productionPublicHtmlFullPath);

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
