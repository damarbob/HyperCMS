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

        // If the file does not exist in both environments, return a 404 error
        if ((!$this->validateDirectory($developmentFullPath, ROOTPATH) || !file_exists($developmentFullPath))
            && (!$this->validateDirectory($productionFullPath, FCPATH) || !file_exists($productionFullPath))
        ) {
            return $this->response->setStatusCode(404, 'File not found');
        }

        // If the development path is valid, use it; otherwise, use the production path
        $fullPath = $developmentFullPath ?: $productionFullPath;

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
    static function hex_decode($input)
    {
        return pack("H*", $input);
    }
}
