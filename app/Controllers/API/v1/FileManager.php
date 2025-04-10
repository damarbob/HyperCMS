<?php

namespace App\Controllers\API\v1;

use Config\Services;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

// @TODO: Translation
class FileManager extends ApiController
{
    private $baseDir;

    public function __construct()
    {
        if (ENVIRONMENT === 'production') {
            $this->baseDir = FCPATH . ''; // For production
        } else {
            $this->baseDir = ROOTPATH . ''; // For development
        }
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

    // @TODO: Review
    public function listFiles($path = '')
    {
        try {
            // Decode and validate input
            $decodedPath = urldecode($this::hex_decode($path));
            if ($decodedPath === false || strpos($decodedPath, "\0") !== false) {
                return $this->response->setStatusCode(400)->setJSON(['error' => lang('Files.invalidPathFormat')]);
            }

            // Build and validate full path
            $fullPath = realpath($this->baseDir . '/' . $decodedPath);
            if ($fullPath === false || !$this->validateDirectory($fullPath, $this->baseDir)) {
                return $this->response->setStatusCode(403)->setJSON(['error' => lang('Files.invalidDirectory')]);
            }

            // Scan directory
            $files = scandir($fullPath);
            if ($files === false) {
                log_message('error', "Failed to scan directory: {$fullPath}");
                return $this->response->setStatusCode(500)->setJSON(['error' => lang('Files.directoryReadError')]);
            }

            $fileList = [];
            foreach (array_diff($files, ['.', '..']) as $file) {
                $currentPath = $fullPath . '/' . $file;

                try {
                    $fileList[] = [
                        'name' => $file,
                        'path' => ltrim($decodedPath . '/' . $file, '/'),
                        'size' => is_dir($currentPath) ? '-' : $this->formatSize(@filesize($currentPath)),
                        'modified_date' => date("Y-m-d H:i:s", @filemtime($currentPath)),
                        'is_dir' => is_dir($currentPath),
                        'permissions' => substr(sprintf('%o', @fileperms($currentPath)), -4)
                    ];
                } catch (\Throwable $e) {
                    // Skip problematic files but continue processing others
                    log_message('debug', "Error processing file {$file}: " . $e->getMessage());
                    continue;
                }
            }

            return $this->response->setJSON($fileList);
        } catch (\Throwable $e) {
            log_message('error', 'File listing error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => lang('Files.serverError')]);
        }
    }

    // @TODO: Improve
    private function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }


    // @INFO: Improved
    public function upload()
    {
        try {
            // Validate CSRF token if not handled globally
            Services::security()->verify($this->request);

            $path = $this->request->getPost('path');
            if (!is_string($path) || strpos($path, "\0") !== false) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => 'Invalid path format']);
            }

            // Build and validate destination path
            $destination = realpath($this->baseDir . '/' . $path) ?: '';
            if (!$destination || !$this->validateDirectory($destination, $this->baseDir)) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON(['error' => 'Invalid destination directory']);
            }

            // Get uploaded file
            $file = $this->request->getFile('file');
            if (!$file || !$file->isValid()) {
                $error = $file->getErrorString() ?: 'No file uploaded';
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => 'Upload failed: ' . $error]);
            }

            // Security checks
            $filename = $file->getName();
            if (preg_match('/[^\w\.\-]/', $filename)) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => 'Invalid filename']);
            }

            // Prevent overwriting existing files
            $targetPath = rtrim($destination, '/') . '/' . $filename;
            if (file_exists($targetPath)) {
                return $this->response
                    ->setStatusCode(409)
                    ->setJSON(['error' => 'File already exists']);
            }

            // Move file with error handling
            if (!$file->move($destination, $filename, true)) {
                throw new \RuntimeException('Failed to move uploaded file');
            }

            // @TODO: Add virus scanning here

            return $this->response
                ->setJSON([
                    'status' => 'success',
                    'filename' => $filename,
                    'path' => ltrim($path . '/' . $filename, '/'),
                ]);
        } catch (\Throwable $e) {
            log_message('error', 'Upload failed: ' . $e->getMessage());
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Server error during upload. ' . $e->getMessage()]);
        }
    }

    // @TODO: Improve
    public function compress()
    {
        $data = $this->request->getJSON(true);
        $selectedFiles = $data['files'];
        $currentPath = realpath($this->baseDir . '/' . $data['path']);

        // d($selectedFiles[0]);
        // dd($currentPath);

        // Determine archive name based on selection
        if (count($selectedFiles) === 1) {
            $archiveName = basename($selectedFiles[0]) . '.zip';
        } else {
            $archiveName = basename($currentPath) . '.zip';
        }
        $zipFilePath = $currentPath . '/' . $this->getUniqueArchiveName($archiveName, $currentPath);

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return $this->response->setJSON(['error' => 'Could not create zip file.']);
        }

        foreach ($selectedFiles as $file) {
            $filePath = realpath($this->baseDir . '/' . $file);
            // dd($this->validateDirectoryWithinBase($filePath, $this->baseDir));
            if ($this->validateDirectoryWithinBase($filePath, $this->baseDir)) {
                $this->addToZipWithoutTopLevel($zip, $filePath, $file);
            }
        }

        $zip->close();
        return $this->response->setJSON(['status' => 'Files compressed', 'archive' => basename($zipFilePath)]);
    }

    // @TODO: Improve
    // Recursive function to add folder contents without including the top-level folder in the archive
    private function addToZipWithoutTopLevel($zip, $filePath, $originalSelectedPath)
    {
        if (is_dir($filePath)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($filePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            // Calculate the relative path length for folder contents
            $cutoffPathLength = strlen(dirname($filePath)) + 1;
            // d($cutoffPathLength);

            foreach ($files as $file) {
                $relativePath = substr($file->getRealPath(), $cutoffPathLength);
                // d($relativePath);

                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($file->getRealPath(), $relativePath);
                }
            }
        } else {
            // Single file addition without folder structure
            $relativePath = basename($filePath);
            $zip->addFile($filePath, $relativePath);
        }
    }

    // @TODO: Improve
    // Helper function to avoid archive name conflicts
    private function getUniqueArchiveName($archiveName, $currentPath)
    {
        $counter = 1;
        $zipName = $archiveName;
        $pathInfo = pathinfo($zipName);
        $baseName = $pathInfo['filename'];

        while (file_exists($currentPath . '/' . $zipName)) {
            $zipName = $baseName . '_' . $counter++ . '.zip';
        }

        return $zipName;
    }

    // @TODO: Improve
    public function extract()
    {
        $data = $this->request->getJSON(true);
        $archive = realpath($this->baseDir . '/' . $data['path']);

        if (!$archive || !file_exists($archive) || pathinfo($archive, PATHINFO_EXTENSION) !== 'zip') {
            return $this->response->setJSON(['error' => 'Invalid zip file path']);
        }

        $zip = new ZipArchive();
        if ($zip->open($archive) === TRUE) {
            $zip->extractTo(dirname($archive));
            $zip->close();
            return $this->response->setJSON(['status' => 'Archive extracted']);
        } else {
            return $this->response->setJSON(['error' => 'Could not open archive']);
        }
    }

    // @TODO: Improve
    public function bulkAction()
    {
        $action = $this->request->getPost('action');
        $files = $this->request->getPost('files');
        $destination = $this->request->getPost('destination');

        foreach ($files as $file) {
            $filePath = realpath($this->baseDir . '/' . $file);

            if (!$this->validateDirectory($filePath, $this->baseDir)) {
                return $this->response->setJSON(['error' => 'Invalid file path']);
            }

            switch ($action) {
                case 'copy':
                    $destPath = $this->baseDir . '/' . $destination . '/' . basename($filePath);
                    copy($filePath, $destPath);
                    break;
                case 'move':
                    $destPath = $this->baseDir . '/' . $destination . '/' . basename($filePath);
                    rename($filePath, $destPath);
                    break;
                case 'delete':
                    if (is_dir($filePath)) {
                        rmdir($filePath);
                    } else {
                        unlink($filePath);
                    }
                    break;
                default:
                    return $this->response->setJSON(['error' => 'Invalid action']);
            }
        }

        return $this->response->setJSON(['status' => 'Bulk action completed']);
    }

    // @TODO: Improve
    public function download($path)
    {
        $filePath = realpath($this->baseDir . '/' . urldecode($this::hex_decode($path)));
        if (!$this->validateDirectory($filePath, $this->baseDir) || !is_file($filePath)) {
            return $this->response->setJSON(['error' => 'Invalid file path']);
        }
        return $this->response->download($filePath, null);
    }

    // @TODO: Improve
    public function viewFile($encodedPath = '')
    {
        $path = urldecode($this::hex_decode($encodedPath)); // Decode the Base64 path
        $fullPath = realpath($this->baseDir . '/' . $path);

        if (!$this->validateDirectory($fullPath, $this->baseDir) || !file_exists($fullPath)) {
            return $this->response->setStatusCode(404, 'File not found');
        }

        // Serve the file content with correct headers
        $mimeType = mime_content_type($fullPath); // @IMPORTANT: Require the fileinfo PHP extension
        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setBody(file_get_contents($fullPath));
    }

    // @TODO: Improve
    public function setClipboard()
    {
        $request = $this->request->getJSON(true);
        $files = $request['files'] ?? [];
        $action = $request['action'] ?? '';

        if (!in_array($action, ['copy', 'move']) || empty($files)) {
            return $this->response->setJSON(['error' => 'Invalid clipboard action or no files selected']);
        }

        // Save files and action in session
        session()->set('clipboard', [
            'files' => $files,
            'action' => $action
        ]);

        return $this->response->setJSON(['status' => 'Clipboard set successfully']);
    }

    // @TODO: Improve
    // Recursive function to copy directories
    /**
     * Recursively copies a directory and its contents to a new location.
     *
     * This function creates a copy of the source directory and all its contents
     * (including subdirectories) at the specified destination.
     *
     * @param string $source      The path to the source directory to be copied.
     * @param string $destination The path where the directory should be copied to.
     *
     * @return void This function does not return a value.
     */
    private function recursiveCopy($source, $destination)
    {
        $dir = opendir($source);
        mkdir($destination);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;

            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
        closedir($dir);
    }


    // @TODO: Improve
    // Recursive function to move directories
    private function recursiveMove($source, $destination)
    {
        if (rename($source, $destination)) return; // Fast path if rename works
        mkdir($destination);

        $dir = opendir($source);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;

            if (is_dir($srcPath)) {
                $this->recursiveMove($srcPath, $destPath);
            } else {
                rename($srcPath, $destPath);
            }
        }
        closedir($dir);
        rmdir($source); // Remove the source directory after moving
    }

    // @TODO: Improve
    public function paste()
    {
        $destination = $this->request->getJSON(true)['destination'] ?? '';
        $clipboard = session()->get('clipboard');

        if (!is_array($clipboard) || !isset($clipboard['files']) || !is_array($clipboard['files'])) {
            return $this->response->setJSON(['error' => 'Clipboard is empty or invalid format']);
        }

        $destinationPath = realpath($this->baseDir . '/' . $destination);
        if (!$destinationPath || !is_dir($destinationPath)) {
            return $this->response->setJSON(['error' => 'Invalid destination path']);
        }

        foreach ($clipboard['files'] as $file) {
            $sourcePath = realpath($this->baseDir . '/' . $file);
            if (!$sourcePath || !file_exists($sourcePath)) {
                return $this->response->setJSON(['error' => "Source file {$file} not found"]);
            }

            $targetPath = $destinationPath . '/' . basename($file);

            // Generate unique filename if file or directory already exists
            if (file_exists($targetPath)) {
                $pathInfo = pathinfo($targetPath);
                $baseName = $pathInfo['filename'];
                $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                $counter = 1;

                do {
                    $newName = $baseName . " ($counter)" . $extension;
                    $targetPath = $destinationPath . '/' . $newName;
                    $counter++;
                } while (file_exists($targetPath));
            }

            // Check if it's a file or directory, then copy/move accordingly
            try {
                if ($clipboard['action'] === 'copy') {
                    if (is_dir($sourcePath)) {
                        $this->recursiveCopy($sourcePath, $targetPath);
                    } else {
                        if (!copy($sourcePath, $targetPath)) {
                            throw new \Exception("Failed to copy {$file}");
                        }
                    }
                } elseif ($clipboard['action'] === 'move') {
                    if (is_dir($sourcePath)) {
                        $this->recursiveMove($sourcePath, $targetPath);
                    } else {
                        if (!rename($sourcePath, $targetPath)) {
                            throw new \Exception("Failed to move {$file}");
                        }
                    }
                }
            } catch (\Exception $e) {
                return $this->response->setJSON(['error' => $e->getMessage()]);
            }
        }

        // Clear clipboard after pasting
        session()->remove('clipboard');
        return $this->response->setJSON(['status' => 'Paste completed']);
    }

    // @TODO: Improve
    public function createFile()
    {
        $data = $this->request->getJSON(true);
        $path = realpath($this->baseDir . '/' . $data['path']);
        $fileName = basename($data['fileName']);
        $newFilePath = $path . '/' . $fileName;

        // Validate the current path and check if file already exists
        if (!$path || !is_dir($path)) {
            return $this->response->setJSON(['error' => 'Invalid directory']);
        }
        if (file_exists($newFilePath)) {
            return $this->response->setJSON(['error' => 'A file with that name already exists']);
        }

        // Attempt to create the file
        try {
            if (!touch($newFilePath)) {
                throw new \Exception('Failed to create file');
            }
            return $this->response->setJSON(['status' => 'File created successfully']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => $e->getMessage()]);
        }
    }

    // @TODO: Improve
    public function createFolder()
    {
        $data = $this->request->getJSON(true);
        $path = realpath($this->baseDir . '/' . $data['path']);
        $folderName = basename($data['folderName']);
        $newFolderPath = $path . '/' . $folderName;

        // Validate the current path and check if folder already exists
        if (!$path || !is_dir($path)) {
            return $this->response->setJSON(['error' => 'Invalid directory']);
        }
        if (file_exists($newFolderPath)) {
            return $this->response->setJSON(['error' => 'A folder with that name already exists']);
        }

        // Attempt to create the folder
        try {
            if (!mkdir($newFolderPath, 0755)) {
                throw new \Exception('Failed to create folder');
            }
            return $this->response->setJSON(['status' => 'Folder created successfully']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => $e->getMessage()]);
        }
    }


    // @TODO: Improve
    public function rename()
    {
        $data = $this->request->getJSON(true);
        $oldPath = realpath($this->baseDir . '/' . $data['oldPath']);
        $newName = $data['newName'];
        $newPath = dirname($oldPath) . '/' . $newName;

        // Validate old file existence
        if (!$oldPath || !file_exists($oldPath)) {
            return $this->response->setJSON(['error' => 'Original file not found']);
        }

        // Check if the new name already exists in the directory
        if (file_exists($newPath)) {
            return $this->response->setJSON(['error' => 'A file with the new name already exists']);
        }

        // Attempt to rename
        try {
            if (!rename($oldPath, $newPath)) {
                throw new \Exception('Failed to rename file');
            }
            return $this->response->setJSON(['status' => 'File renamed successfully']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => $e->getMessage()]);
        }
    }

    // @TODO: Improve
    public function saveFile()
    {
        $data = json_decode($this->request->getBody(), true);
        $relativePath = $data['path'] ?? ''; // Fetch and decode path, defaulting to root
        $content = $data['content'] ?? '';

        // Construct the full path safely using the base directory
        $filePath = realpath($this->baseDir . '/' . ($relativePath ? urldecode($this::hex_decode($relativePath)) : ''));

        if (!$this->validateDirectory($filePath, $this->baseDir)) {
            return $this->response->setJSON(['error' => 'Invalid file path']);
        }

        // Attempt to save content
        if (file_put_contents($filePath, $content) !== false) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['success' => false, 'error' => 'Failed to save file.']);
        }
    }

    // @TODO: Improve
    // Recursive function to delete non-empty directories
    private function recursiveDelete($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        return rmdir($dir);
    }

    // @TODO: Improve
    public function deleteFiles()
    {
        $files = $this->request->getJSON(true)['files'] ?? [];

        if (empty($files)) {
            return $this->response->setJSON(['error' => 'No files selected for deletion']);
        }

        foreach ($files as $file) {
            $filePath = realpath($this->baseDir . '/' . $file);

            if (!$filePath || !file_exists($filePath)) {
                return $this->response->setJSON(['error' => "File {$file} not found"]);
            }

            if (is_file($filePath)) {
                unlink($filePath);
            } elseif (is_dir($filePath)) {
                $this->recursiveDelete($filePath); // Use recursive delete for non-empty directories
            }
        }

        return $this->response->setJSON(['status' => 'Files deleted successfully']);
    }

    // @TODO: Improve
    static function hex_encode($input)
    {
        return bin2hex($input);
    }

    // @TODO: Improve
    static function hex_decode($input)
    {
        return pack("H*", $input);
    }
}
