<?php

namespace App\Controllers\API\v1;

// @TODO: Translation
class FileServer extends ApiController
{
    // Similar to FileManager::viewFile but more adaptive across environments (less security checks for published files)
    // 'Published files' means files either from ROOTPATH or FCPATH that are decided to be served to the public
    // @IMPORTANT: ROOTPATH as development environment and FCPATH as production environment DEPENDS on FileManager's baseDir setting in the constructor
    public function serve($encodedPath = '')
    {
        $path = urldecode($this::hex_decode($encodedPath)); // Decode the Base64 path
        $developmentFullPath = realpath(ROOTPATH . '/' . $path); // Get the full path in the development environment
        $productionFullPath = realpath(FCPATH . '/' . $path); // Get the full path in the production environment

        // For deployments in hosting with public_html folder
        $productionPublicHtmlFullPath = realpath(FCPATH . '/' . $this::replacePublicFolder($path, ""));

        // If the file does not exist in both environments, return a 404 error
        if ((!$this->validateDirectory($developmentFullPath, ROOTPATH) || !file_exists($developmentFullPath))
            && (!$this->validateDirectory($productionFullPath, FCPATH) || !file_exists($productionFullPath))
            && (!$this->validateDirectoryWithinBase($productionPublicHtmlFullPath, FCPATH) || !file_exists($productionPublicHtmlFullPath))
        ) {
            return $this->response->setStatusCode(404, 'File not found');
        }

        // If the development path is valid, use it; otherwise, use the production path
        $fullPath = $developmentFullPath ?: ($productionFullPath ?: $productionPublicHtmlFullPath);

        // Serve the file content with correct headers
        $mimeType = mime_content_type($fullPath); // @IMPORTANT: Require the fileinfo PHP extension
        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setBody(file_get_contents($fullPath));
    }

    // @TODO: Improve
    private function validateDirectory(string $directory, string $baseDir): bool
    {
        // Ensure both paths have no trailing slashes
        $normalizedBaseDir = rtrim($baseDir, '/\\');
        $normalizedRelativePath = rtrim($directory, '/\\');

        // Check if the path is within the base directory
        if (!$normalizedRelativePath || strpos($normalizedRelativePath, $normalizedBaseDir) !== 0) {
            return false;
        }

        return true;
    }

    // @TODO: Improve
    // Modified validateDirectory to allow folders in base directory
    private function validateDirectoryWithinBase(string $directory, string $baseDir): bool
    {
        $normalizedBaseDir = rtrim($baseDir, '/\\');
        $normalizedDirectory = rtrim($directory, '/\\');

        // Check if directory is the same as or within the base directory
        return $normalizedDirectory === $normalizedBaseDir || strpos($normalizedDirectory, $normalizedBaseDir) === 0;
    }

    // @TODO: Improve
    static function hex_decode($input)
    {
        return pack("H*", $input);
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
