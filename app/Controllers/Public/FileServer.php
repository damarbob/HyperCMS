<?php

namespace App\Controllers\Public;

use App\Controllers\API\v1\ApiController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Mimes;

class FileServer extends ApiController
{
    /**
     * Helpers to be loaded automatically.
     *
     * The 'hyper_hex' helper is required for hex_encode and hex_decode functions.
     * The 'hyper_directory' helper is required for directory path validation.
     *
     * @var array
     */
    protected $helpers = ['hyper_hex', 'hyper_directory'];

    // Similar to FileManager::viewFile but more adaptive across environments (less security checks for published files)
    // 'Published files' means files either from ROOTPATH or FCPATH that are decided to be served to the public
    // @IMPORTANT: ROOTPATH as development environment and FCPATH as production environment DEPENDS on FileManager's baseDir setting in the constructor
    public function serve($encodedPath = '')
    {
        $path = urldecode(hex_decode($encodedPath)); // Decode the Base64 path
        $developmentFullPath = realpath(ROOTPATH . '/' . $path); // Get the full path in the development environment
        $productionFullPath = realpath(FCPATH . '/' . $path); // Get the full path in the production environment

        // For deployments in hosting with public_html folder
        $productionPublicHtmlFullPath = realpath(FCPATH . '/' . $this::replacePublicFolder($path, ""));

        // If the file does not exist in both environments, return a 404 error
        if ((!validate_directory($developmentFullPath, ROOTPATH) || !file_exists($developmentFullPath))
            && (!validate_directory($productionFullPath, FCPATH) || !file_exists($productionFullPath))
            && (!validate_directory_within_base($productionPublicHtmlFullPath, FCPATH) || !file_exists($productionPublicHtmlFullPath))
        ) {
            throw PageNotFoundException::forPageNotFound(lang('Admin.fileNotFound'));
        }

        // If the development path is valid, use it; otherwise, use the production path
        $fullPath = $developmentFullPath ?: ($productionFullPath ?: $productionPublicHtmlFullPath);

        // @IMPORTANT: This feature require fileinfo PHP extension
        // Serve the file content with correct headers
        // Use CodeIgniter's File class to handle MIME detection
        $file = new \CodeIgniter\Files\File($fullPath);
        $mimeType = Mimes::guessTypeFromExtension($file->getExtension()); // More reliable way to guess MIME

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setBody(file_get_contents($fullPath));
    }

    /**
     * Replace the initial "public" folder with "public_html" in a given path.
     *
     * @param string $path The original path.
     * @param string $folderName The expected folder name.
     * @return string The updated path with "public" replaced by "public_html".
     */
    static function replacePublicFolder(string $path, string $folderName): string
    {
        // Normalize directory separators to ensure consistency across platforms
        $normalizedPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

        // Split the path into components
        $pathComponents = explode(DIRECTORY_SEPARATOR, $normalizedPath);

        // Check if the first component is "public"
        if (isset($pathComponents[0]) && $pathComponents[0] === 'public') {
            // Replace "public" with "public_html"
            $pathComponents[0] = $folderName;
        }

        // Rebuild the path
        return implode(DIRECTORY_SEPARATOR, $pathComponents);
    }
}
