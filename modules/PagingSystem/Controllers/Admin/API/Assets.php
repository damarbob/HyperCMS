<?php

namespace PagingSystem\Controllers\Admin\API;

use App\Controllers\API\v1\ApiController;
use CodeIgniter\HTTP\Files\UploadedFile;
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
class Assets extends ApiController
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

    // Path where uploaded files will be stored, varies by environment
    protected string $uploadDestination;

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
        /** @var \Config\FileManager */
        $fileManagerConfig = config('fileManager');

        if (ENVIRONMENT === 'production') {
            $this->baseDir = $fileManagerConfig->productionPath;
        } else {
            $this->baseDir = $fileManagerConfig->nonProductionPath;
        }

        // Determine upload directory based on current environment
        if (ENVIRONMENT === 'production') {
            $this->uploadDestination = 'uploads' . DIRECTORY_SEPARATOR;
        } else {
            $this->uploadDestination = '.hyper-dev' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        }
    }

    public function assetsUpload()
    {
        // Process file uploads. This returns an array of file URLs and a log array.
        list($fileUrls, $fileLog) = $this->processUploadedFiles();

        if (!empty($fileLog['file_errors'])) {

            $fileErrors = [];
            foreach ($fileLog['file_errors'] as $error) {
                array_push($fileErrors, $error['error_message']);
            }

            return $this->response->setJSON([
                'status'        => 'error',
                'message'       => implode(' ', $fileErrors)
            ]);
        }

        return $this->response->setJSON([
            'status'    => 'success',
            // 'message'   => lang('PagingSystem.assetsUploaded')
            // 'message'   => implode(' ', $fileUrls)
            'message'   => $fileUrls['files'],
            'data'      => array_map(fn($file) => base_url('public/file-server/serve/' . hex_encode($file)), $fileUrls['files'])
        ]);
    }
    /**
     * Process any uploaded files.
     *
     * Loops through the uploaded files, moves them to a designated folder,
     * and collects the resulting URLs.
     *
     * @return array [fileUrls, log]
     */
    protected function processUploadedFiles()
    {
        $files = $this->request->getFiles();
        $fileUrls = [];
        $log = [];


        if ($files) {
            foreach ($files as $fileInputName => $uploadedFiles) {
                // Ensure it's an array (wrap single file in an array).
                if (!is_array($uploadedFiles)) {
                    $uploadedFiles = [$uploadedFiles];
                }

                foreach ($uploadedFiles as $file) {
                    if ($file instanceof UploadedFile) {
                        if ($file->isValid() && ! $file->hasMoved()) {
                            // Log file details (for debugging purposes).
                            $log['file_details'][] = [
                                'input_name' => $fileInputName,
                                'original_name' => $file->getClientName(),
                                'temp_path' => $file->getTempName(),
                                'file_size' => $file->getSize(),
                                'file_type' => $file->getMimeType(),
                            ];

                            // Use a slugified original name and a random name component.
                            $originalName = url_title(pathinfo($file->getClientName(), PATHINFO_FILENAME), '-', false);
                            $randomName = $file->getRandomName();

                            // Final destination path (adjust as necessary).
                            $destination = FCPATH . $this->uploadDestination;

                            // Move the file.
                            $file->move($destination, $originalName . '-' . $randomName);

                            // Record the relative URL.
                            $fileUrls[$fileInputName][] = $this->uploadDestination . $originalName . '-' . $randomName;
                        } else {
                            // If the file is invalid, log the error.
                            $log['file_errors'][] = [
                                'input_name' => $fileInputName,
                                'error_message' => $file->getErrorString(),
                            ];
                        }
                    }
                }
            }
        }

        return [$fileUrls, $log];
    }
}
