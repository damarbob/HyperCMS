<?php

namespace App\Controllers\API\v1;

use Config\Services;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Class FileManager
 *
 * An API controller that handles file management operations.
 * 
 * This class is responsible for various file operations such as
 * compressing, extracting, copying, moving, creating, renaming,
 * and deleting files/folders.
 *
 * It automatically loads the 'hex' helper used for encoding and decoding
 * operations, and it sets up the base directory for file operations based on
 * the current environment.
 *
 * @package App\Controllers
 */
class FileManager extends ApiController
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

    /**
     * Base directory for file management operations.
     *
     * In a production environment, this is set to FCPATH (e.g., public directory).
     * In a development environment, this is set to ROOTPATH.
     *
     * @var string
     */
    private $baseDir;

    /**
     * Constructor
     *
     * Initializes the base directory property according to the current environment.
     *
     * Production: Uses FCPATH.
     * Development: Uses ROOTPATH.
     */
    public function __construct()
    {
        if (ENVIRONMENT === 'production') {
            $this->baseDir = FCPATH; // For production use, FCPATH is typically the public folder.
        } else {
            $this->baseDir = ROOTPATH; // For development use, ROOTPATH could reference the application root.
        }
    }

    /**
     * List files in the specified directory.
     *
     * The method decodes a hex-encoded, URL-encoded relative path,
     * validates it against the base directory, scans the directory,
     * and returns a JSON response listing file details.
     *
     * @param string $path The hex-encoded, URL-encoded relative directory path.
     * @return \CodeIgniter\HTTP\Response JSON response with file list data or error.
     */
    public function listFiles($path = '')
    {
        try {
            // Decode and Validate Input Path
            $decodedPath = urldecode(hex_decode($path));

            // Reject if decoding fails or if a NULL-byte is present
            if ($decodedPath === false || strpos($decodedPath, "\0") !== false) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => lang('Admin.invalidPathFormat')]);
            }

            // Build and Validate Full Path
            // Append the decoded path to the base directory and resolve to its canonicalized absolute pathname.
            $fullPath = realpath($this->baseDir . '/' . $decodedPath);
            if ($fullPath === false || !validate_directory($fullPath, $this->baseDir)) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON(['error' => lang('Admin.invalidDirectory')]);
            }

            // Scan the Directory
            $files = scandir($fullPath);
            if ($files === false) {
                log_message('error', "Failed to scan directory: {$fullPath}");
                return $this->response
                    ->setStatusCode(500)
                    ->setJSON(['error' => lang('Admin.directoryReadError')]);
            }

            // Build the File List
            $fileList = [];
            // Exclude the current and parent directory entries ('.' and '..')
            foreach (array_diff($files, ['.', '..']) as $file) {
                $currentPath = $fullPath . '/' . $file;
                try {
                    $fileList[] = [
                        'name'          => $file,
                        'path'          => ltrim($decodedPath . '/' . $file, '/'),
                        'size'          => is_dir($currentPath) ? '-' : $this->formatSize(@filesize($currentPath)),
                        'modified_date' => date("Y-m-d H:i:s", @filemtime($currentPath)),
                        'is_dir'        => is_dir($currentPath),
                        'permissions'   => substr(sprintf('%o', @fileperms($currentPath)), -4),
                    ];
                } catch (\Throwable $e) {
                    // Log the error and skip this file, continue processing the rest.
                    log_message('debug', "Error processing file {$file}: " . $e->getMessage());
                    continue;
                }
            }

            return $this->response->setJSON($fileList);
        } catch (\Throwable $e) {
            log_message('error', 'File listing error: ' . $e->getMessage());
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => lang('Files.serverError')]);
        }
    }

    /**
     * Convert a file size in bytes to a human-readable string.
     *
     * The method automatically selects the appropriate unit (B, KB, MB, GB, TB)
     * by iteratively dividing the value by 1024.
     *
     * @param int $bytes The file size in bytes.
     * @return string The formatted file size with up to two decimals.
     */
    private function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Handle the file upload process.
     *
     * This method verifies the CSRF token, validates the
     * destination directory (using the posted path), performs filename
     * sanitization (e.g., replacing spaces with underscores), ensures the target
     * location does not overwrite an existing file, and then moves the file.
     *
     * A virus scan could be added at the indicated location if needed.
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating upload success or error.
     */
    public function upload()
    {
        try {
            // Validate CSRF Token
            Services::security()->verify($this->request);

            // Validate the Destination Path from POST Data
            $path = $this->request->getPost('path');
            if (!is_string($path) || strpos($path, "\0") !== false) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => lang('Admin.invalidPathFormat')]);
            }

            // Build the full destination path.
            $destination = realpath($this->baseDir . '/' . $path) ?: '';
            if (!$destination || !validate_directory($destination, $this->baseDir)) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON(['error' => lang('Admin.invalidDestinationDirectory')]);
            }

            // Retrieve the Uploaded File
            $file = $this->request->getFile('file');
            if (!$file || !$file->isValid()) {
                $error = $file->getErrorString() ?? lang('Admin.noFileUploaded');
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => lang('Admin.uploadFailedx', ['x' => $error])]);
            }

            // Security Checks on Filename
            $filename = $file->getName();
            // Replace spaces with underscores for safety.
            $safeFilename = str_replace(' ', '_', $filename);
            // Ensure the filename only contains allowed characters (letters, digits, underscores, dots, hyphens).
            if (preg_match('/[^\w\.\-]/', $safeFilename)) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => lang('Admin.invalidFilename')]);
            }

            // Prevent File Overwrites
            $destinationPath = rtrim($destination, '/');
            $targetPath = $destinationPath . '/' . $safeFilename;
            if (file_exists($targetPath)) {
                $pathInfo = pathinfo($targetPath);
                $baseName = $pathInfo['filename'];
                $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                $counter = 1;
                do {
                    $safeFilename = $baseName . " ($counter)" . $extension;
                    $targetPath = $destinationPath . '/' . $safeFilename;
                    $counter++;
                } while (file_exists($targetPath));
            }

            // Move the File
            if (!$file->move($destination, $safeFilename, true)) {
                throw new \RuntimeException(lang('Admin.failedToMoveUploadedFile'));
            }

            // TODO: Add virus scanning here as needed.

            // Return Success Response
            return $this->response->setJSON([
                'status'   => 'success',
                'filename' => $safeFilename,
                'path'     => ltrim($path . '/' . $safeFilename, '/'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Upload failed: ' . $e->getMessage());
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => lang('Admin.serverErrorDuringUploadx', ['x' => $e->getMessage()])]);
        }
    }

    /**
     * Compress the requested files or folder into a ZIP archive.
     *
     * Expects a JSON payload containing:
     * - 'files': An array of file paths (relative to baseDir) that should be compressed.
     * - 'path': The current (relative) path.
     *
     * If a single file is selected, the archive name is based on that file.
     * Otherwise, it is based on the current directory name.
     *
     * @return \CodeIgniter\HTTP\Response JSON response with status and archive name, or an error message.
     */
    public function compress()
    {
        // Retrieve posted JSON data.
        $data = $this->request->getJSON(true);
        $selectedFiles = $data['files'];
        $currentPath = realpath($this->baseDir . '/' . $data['path']);

        // Determine archive name based on selection.
        if (count($selectedFiles) === 1) {
            $archiveName = basename($selectedFiles[0]) . '.zip';
        } else {
            $archiveName = basename($currentPath) . '.zip';
        }
        // Avoid name conflicts.
        $zipFilePath = $currentPath . '/' . $this->getUniqueArchiveName($archiveName, $currentPath);

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->response->setJSON(['error' => lang('Admin.couldNotCreateZipFile')]);
        }

        // Add each selected file/folder into the ZIP if its location is valid.
        foreach ($selectedFiles as $file) {
            $filePath = realpath($this->baseDir . '/' . $file);

            if (validate_directory_within_base($filePath, $this->baseDir)) {
                $this->addToZipWithoutTopLevel($zip, $filePath, $file);
            }
        }

        $zip->close();

        return $this->response->setJSON([
            'status'  => lang('Admin.filesCompressed'),
            'archive' => basename($zipFilePath)
        ]);
    }

    /**
     * Recursively add folder contents to a ZipArchive without including the top-level folder.
     *
     * If $filePath is a directory, recursively traverse its contents and include each
     * file/folder using a relative path that omits the top-level folder. For files,
     * they are directly added using their base names.
     *
     * @param ZipArchive $zip The ZIP archive instance.
     * @param string     $filePath The absolute file or folder path.
     * @param string     $originalSelectedPath The originally selected path (for relative referencing).
     * @return void
     */
    private function addToZipWithoutTopLevel($zip, $filePath, $originalSelectedPath)
    {
        if (is_dir($filePath)) {
            // Create an iterator for all files in the directory.
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($filePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            // Calculate the relative path cutoff length (length of the folder's parent with slash)
            $cutoffPathLength = strlen(dirname($filePath)) + 1;

            foreach ($files as $file) {
                $relativePath = substr($file->getRealPath(), $cutoffPathLength);
                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($file->getRealPath(), $relativePath);
                }
            }
        } else {
            // For a single file, simply add it with its base name.
            $relativePath = basename($filePath);
            $zip->addFile($filePath, $relativePath);
        }
    }

    /**
     * Generate a unique archive name to avoid name conflicts in the destination directory.
     *
     * If a file with the proposed archive name exists in $currentPath, a numeric suffix is appended.
     *
     * @param string $archiveName The initial archive name (e.g., "archive.zip").
     * @param string $currentPath The destination directory for the archive.
     * @return string A unique archive filename.
     */
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

    /**
     * Extract a ZIP archive.
     *
     * Expects a JSON payload with a 'path' property specifying the relative path of the archive.
     * Validates the archive and extracts its content to the same directory.
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating extraction status.
     */
    public function extract()
    {
        $data = $this->request->getJSON(true);
        $archive = realpath($this->baseDir . '/' . $data['path']);

        // Validate that the provided file exists and has a .zip extension.
        if (!$archive || !file_exists($archive) || pathinfo($archive, PATHINFO_EXTENSION) !== 'zip') {
            return $this->response->setJSON(['error' => lang('Admin.invalidZipFilePath')]);
        }

        $zip = new ZipArchive();
        if ($zip->open($archive) === true) {
            $zip->extractTo(dirname($archive));
            $zip->close();

            return $this->response->setJSON(['status' => lang('Admin.archiveExtracted')]);
        } else {
            return $this->response->setJSON(['error' => lang('Admin.couldNotOpenArchive')]);
        }
    }

    /**
     * Execute a bulk action (copy, move, or delete) on multiple files.
     *
     * Expects POST parameters:
     * - 'action': The action to perform ("copy", "move", or "delete").
     * - 'files': An array of file paths (relative to baseDir) to act on.
     * - 'destination': (For copy/move) The target folder.
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating the status of the bulk operation.
     */
    public function bulkAction()
    {
        $action      = $this->request->getPost('action');
        $files       = $this->request->getPost('files');
        $destination = $this->request->getPost('destination');

        foreach ($files as $file) {
            $filePath = realpath($this->baseDir . '/' . $file);

            // Validate the file path is within the base directory.
            if (!validate_directory($filePath, $this->baseDir)) {
                return $this->response->setJSON(['error' => lang('Admin.invalidFilePath')]);
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
                        // For directories, ensure they are empty before removing;
                        // consider recursive deletion if needed.
                        rmdir($filePath);
                    } else {
                        unlink($filePath);
                    }
                    break;

                default:
                    return $this->response->setJSON(['error' => lang('Admin.invalidAction')]);
            }
        }

        return $this->response->setJSON(['status' => lang('Admin.bulkActionCompleted')]);
    }

    /**
     * Serve a file download.
     *
     * The $path parameter is hex-encoded and URL-encoded.
     * After decoding and validating the file path, returns a download response.
     *
     * @param string $path The encoded file path.
     * @return \CodeIgniter\HTTP\Response A download response containing the file.
     */
    public function download($path)
    {
        $filePath = realpath($this->baseDir . '/' . urldecode(hex_decode($path)));

        // Validate the file is within the allowed directory and exists.
        if (!validate_directory($filePath, $this->baseDir) || !is_file($filePath)) {
            return $this->response->setJSON(['error' => lang('Admin.invalidFilePath')]);
        }
        return $this->response->download($filePath, null);
    }

    /**
     * Display the content of a file in the browser.
     *
     * The provided $encodedPath is hex-encoded and URL-encoded. The method decodes,
     * validates that the file exists and that it resides within the base directory, then
     * returns the file contents with the appropriate MIME type headers.
     *
     * @param string $encodedPath The encoded file path.
     * @return \CodeIgniter\HTTP\Response The response containing file content or a 404 error.
     */
    public function viewFile($encodedPath = '')
    {
        // Decode the encoded path.
        $path = urldecode(hex_decode($encodedPath));
        $fullPath = realpath($this->baseDir . '/' . $path);

        // Validate the file existence and location.
        if (!validate_directory($fullPath, $this->baseDir) || !file_exists($fullPath)) {
            return $this->response->setStatusCode(404, lang('Admin.fileNotFound'));
        }

        // Identify the MIME type (requires the fileinfo PHP extension).
        $mimeType = mime_content_type($fullPath);

        // Serve the file content along with appropriate headers.
        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setBody(file_get_contents($fullPath));
    }

    /**
     * Set clipboard content for file operations (copy or move).
     *
     * This method expects a JSON payload with:
     *   - 'files': An array of file paths (relative to baseDir) to be copied or moved.
     *   - 'action': A string, either "copy" or "move".
     *
     * The clipboard (files and the associated action) is stored in session.
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating success or an error.
     */
    public function setClipboard()
    {
        // Get JSON data from request.
        $request = $this->request->getJSON(true);
        $files  = $request['files'] ?? [];
        $action = $request['action'] ?? '';

        // Validate the action and that at least one file is selected.
        if (!in_array($action, ['copy', 'move']) || empty($files)) {
            return $this->response->setJSON([
                'error' => lang('Admin.invalidClipboardActionNoFilesSelected')
            ]);
        }

        // Save selected files and the chosen action into session.
        session()->set('clipboard', [
            'files'  => $files,
            'action' => $action,
        ]);

        return $this->response->setJSON([
            'status' => lang('Admin.clipboardSetSuccessfully')
        ]);
    }

    /**
     * Recursively copy a directory and its contents to a destination.
     *
     * This function will copy the entire contents (files and subdirectories)
     * from the source directory into the destination directory.
     *
     * @param string $source      Absolute path of the source directory.
     * @param string $destination Absolute path where the directory should be copied.
     *
     * @return void
     */
    private function recursiveCopy($source, $destination)
    {
        // Open the source directory.
        $dir = opendir($source);
        // Create the destination directory.
        mkdir($destination);

        // Loop through files in the source directory.
        while (($file = readdir($dir)) !== false) {
            // Skip the special entries "." and "..".
            if ($file === '.' || $file === '..') continue;

            $srcPath  = $source . '/' . $file;
            $destPath = $destination . '/' . $file;

            // Recurse if it's a directory; otherwise, copy the file.
            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
        closedir($dir);
    }


    /**
     * Recursively move a directory and its contents to a destination.
     *
     * First, attempt a fast-path move using rename().
     * If that fails (e.g. moving across file systems), move files and directories recursively.
     * Finally, remove the source directory.
     *
     * @param string $source      Absolute path of the source directory.
     * @param string $destination Absolute path for the moved directory.
     *
     * @return void
     */
    private function recursiveMove($source, $destination)
    {
        // Attempt to move the directory with rename().
        if (rename($source, $destination)) return;

        // If rename fails, create the destination and move contents recursively.
        mkdir($destination);
        $dir = opendir($source);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $srcPath  = $source . '/' . $file;
            $destPath = $destination . '/' . $file;

            if (is_dir($srcPath)) {
                $this->recursiveMove($srcPath, $destPath);
            } else {
                rename($srcPath, $destPath);
            }
        }
        closedir($dir);
        // Remove the source directory once all files have been moved.
        rmdir($source);
    }

    /**
     * Paste clipboard contents to the specified destination.
     *
     * Expects a JSON payload with a 'destination' property (relative path).
     * The function retrieves the clipboard from session (holding files & action),
     * validates the destination directory, and performs the copy/move operation.
     *
     * If a file/folder already exists at the destination, a unique filename is generated.
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating success or error.
     */
    public function paste()
    {
        // Retrieve destination from request JSON.
        $destination = $this->request->getJSON(true)['destination'] ?? '';
        // Retrieve clipboard contents from session.
        $clipboard = session()->get('clipboard');

        // Validate clipboard structure.
        if (!is_array($clipboard) || !isset($clipboard['files']) || !is_array($clipboard['files'])) {
            return $this->response->setJSON([
                'error' => lang('Admin.clipboardEmptyInvalidFormat')
            ]);
        }

        // Resolve destination directory and validate it.
        $destinationPath = realpath($this->baseDir . '/' . $destination);
        if (!$destinationPath || !is_dir($destinationPath)) {
            return $this->response->setJSON([
                'error' => lang('Admin.invalidDestinationPath')
            ]);
        }

        // Process each file or directory from the clipboard.
        foreach ($clipboard['files'] as $file) {
            $sourcePath = realpath($this->baseDir . '/' . $file);
            if (!$sourcePath || !file_exists($sourcePath)) {
                return $this->response->setJSON([
                    'error' => lang('Admin.sourcexFileNotFound', ['x' => $file])
                ]);
            }

            // Build the target path (destination + basename of source).
            $targetPath = $destinationPath . '/' . basename($file);

            // Generate a unique filename if conflict exists.
            if (file_exists($targetPath)) {
                $pathInfo  = pathinfo($targetPath);
                $baseName  = $pathInfo['filename'];
                $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
                $counter   = 1;

                do {
                    $newName   = $baseName . " ($counter)" . $extension;
                    $targetPath = $destinationPath . '/' . $newName;
                    $counter++;
                } while (file_exists($targetPath));
            }

            // Execute copy or move based on clipboard action.
            try {
                if ($clipboard['action'] === 'copy') {
                    if (is_dir($sourcePath)) {
                        $this->recursiveCopy($sourcePath, $targetPath);
                    } else {
                        if (!copy($sourcePath, $targetPath)) {
                            throw new \Exception(lang('Admin.failedToCopyx', ['x' => $file]));
                        }
                    }
                } elseif ($clipboard['action'] === 'move') {
                    if (is_dir($sourcePath)) {
                        $this->recursiveMove($sourcePath, $targetPath);
                    } else {
                        if (!rename($sourcePath, $targetPath)) {
                            throw new \Exception(lang('Admin.failedToMovex', ['x' => $file]));
                        }
                    }
                }
            } catch (\Exception $e) {
                return $this->response->setJSON(['error' => $e->getMessage()]);
            }
        }

        // Clear the clipboard content from session after a successful paste.
        session()->remove('clipboard');
        return $this->response->setJSON([
            'status' => lang('Admin.pasteCompleted')
        ]);
    }

    /**
     * Create a new file in a specified directory.
     *
     * Expects a JSON payload with:
     *  - 'path': The relative target directory.
     *  - 'fileName': The name of the file to create.
     *
     * Validates that the directory exists and that no file with the same name exists.
     * If validations pass, creates an empty file using PHP's touch() function.
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating success or error.
     */
    public function createFile()
    {
        $data     = $this->request->getJSON(true);
        $path     = realpath($this->baseDir . '/' . $data['path']);
        $fileName = basename($data['fileName']); // Sanitize file name by stripping path information.
        $newFilePath = $path . '/' . $fileName;

        // Validate directory existence.
        if (!$path || !is_dir($path)) {
            return $this->response->setJSON(['error' => lang('Admin.invalidDirectory')]);
        }
        // Prevent creating file if one already exists with the same name.
        if (file_exists($newFilePath)) {
            if (is_dir($newFilePath)) {
                return $this->response->setJSON(['error' => lang('Admin.folderWithThatNameAlreadyExists')]);
            } else {
                // A file exists with that name.
                return $this->response->setJSON(['error' => lang('Admin.fileWithThatNameAlreadyExists')]);
            }
        }

        // Attempt to create the file.
        try {
            if (!touch($newFilePath)) {
                throw new \Exception(lang('Admin.failedToCreateFile'));
            }
            return $this->response->setJSON(['status' => lang('Admin.fileCreatedSuccessfully')]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Create a new folder in a specified directory.
     *
     * Expects a JSON payload with:
     *  - 'path': The relative target directory.
     *  - 'folderName': The name of the folder to create.
     *
     * Validates that the target directory exists and that no folder with the same name exists.
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating success or error.
     */
    public function createFolder()
    {
        $data       = $this->request->getJSON(true);
        $path       = realpath($this->baseDir . '/' . $data['path']);
        $folderName = basename($data['folderName']); // Sanitize folder name.
        $newFolderPath = $path . '/' . $folderName;

        // Validate that the target directory exists.
        if (!$path || !is_dir($path)) {
            return $this->response->setJSON(['error' => lang('Admin.invalidDirectory')]);
        }
        // Check for conflicting folder name.
        if (file_exists($newFolderPath)) {
            if (is_dir($newFolderPath)) {
                return $this->response->setJSON(['error' => lang('Admin.folderWithThatNameAlreadyExists')]);
            } else {
                // A file exists with that name.
                return $this->response->setJSON(['error' => lang('Admin.fileWithThatNameAlreadyExists')]);
            }
        }


        // Attempt to create the folder.
        try {
            if (!mkdir($newFolderPath, 0755)) {
                throw new \Exception(lang('Admin.failedToCreateFolder'));
            }
            return $this->response->setJSON(['status' => lang('Admin.folderCreatedSuccessfully')]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Rename a file or folder.
     *
     * Expects a JSON payload with:
     *  - 'oldPath': The relative path of the original file/folder.
     *  - 'newName': The new name for the file/folder.
     *
     * Validates that the source exists and ensures that a new file/folder with the target
     * name does not already exist in the same directory.
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating success or error.
     */
    public function rename()
    {
        $data    = $this->request->getJSON(true);
        $oldPath = realpath($this->baseDir . '/' . $data['oldPath']);
        $newName = $data['newName'];
        $newPath = dirname($oldPath) . '/' . $newName;

        // Validate that the original file or folder exists.
        if (!$oldPath || !file_exists($oldPath)) {
            return $this->response->setJSON(['error' => lang('Admin.originalFileNotFound')]);
        }
        // Check for file/folder name conflicts.
        if (file_exists($newPath)) {
            return $this->response->setJSON(['error' => lang('Admin.fileWithNewNameAlreadyExists')]);
        }

        // Attempt renaming.
        try {
            if (!rename($oldPath, $newPath)) {
                throw new \Exception(lang('Admin.failedToRenameFile'));
            }
            return $this->response->setJSON(['status' => lang('Admin.fileRenamedSuccessfully')]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Save content to a file.
     *
     * Expects a JSON payload with:
     *  - 'path': The relative, encoded file path.
     *  - 'content': The content to be written to the file.
     *
     * Decodes the given path and validates that it resides within the base directory.
     * Saves the content using file_put_contents().
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating success or error.
     */
    public function saveFile()
    {
        // Decode the JSON request body.
        $data = json_decode($this->request->getBody(), true);
        $relativePath = $data['path'] ?? ''; // Relative path to the file.
        $content = $data['content'] ?? '';

        // Decode the path (if provided) and resolve it relative to baseDir.
        $filePath = realpath($this->baseDir . '/' . ($relativePath ? urldecode(hex_decode($relativePath)) : ''));

        // Validate that filePath is allowed.
        if (!validate_directory($filePath, $this->baseDir)) {
            return $this->response->setJSON(['error' => lang('Admin.invalidFilePath')]);
        }

        // Write content to the file.
        if (file_put_contents($filePath, $content) !== false) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['success' => false, 'error' => lang('Admin.failedToSaveFile')]);
        }
    }

    /**
     * Recursively delete a directory including all of its contents.
     *
     * This function will delete all files and subdirectories within the given directory,
     * and then remove the directory itself.
     *
     * @param string $dir The directory path to delete.
     * @return bool True on success, false on failure.
     */
    private function recursiveDelete($dir)
    {
        // Scan for all contents, excluding the special '.' and '..' entries.
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            // If the path is a directory, call recursively; else, delete the file.
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        // Finally, remove the directory itself.
        return rmdir($dir);
    }

    /**
     * Delete multiple files or directories.
     *
     * Expects a JSON payload with:
     *  - 'files': An array of file paths (relative to baseDir) to delete.
     *
     * Validates that each file or folder exists. For directories, performs a recursive deletion.
     *
     * @return \CodeIgniter\HTTP\Response JSON response indicating success or an error.
     */
    public function deleteFiles()
    {
        $files = $this->request->getJSON(true)['files'] ?? [];

        if (empty($files)) {
            return $this->response->setJSON(['error' => lang('Admin.noFilesSelectedForDeletion')]);
        }

        foreach ($files as $file) {
            $filePath = realpath($this->baseDir . '/' . $file);
            // Ensure the file or folder exists.
            if (!$filePath || !file_exists($filePath)) {
                return $this->response->setJSON(['error' => lang('Admin.filexNotFound', ['x' => $file])]);
            }
            // Delete file or recursively delete directory.
            if (is_file($filePath)) {
                unlink($filePath);
            } elseif (is_dir($filePath)) {
                $this->recursiveDelete($filePath);
            }
        }

        return $this->response->setJSON(['status' => lang('Admin.filesDeletedSuccessfully')]);
    }
}
