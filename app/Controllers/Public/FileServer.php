<?php

namespace App\Controllers\Public;

use App\Controllers\API\v1\ApiController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Mimes;

class FileServer extends ApiController
{

    // Similar to FileManager::viewFile but more adaptive across environments (less security checks for published files)
    // 'Published files' means files either from ROOTPATH or FCPATH that are decided to be served to the public
    // @IMPORTANT: ROOTPATH as development environment and FCPATH as production environment DEPENDS on FileManager's baseDir setting in the constructor
    public function serve($encodedPath = '')
    {
        // Get the fileServer service.
        /** @var \App\Services\FileServer */
        $fileServer = service('fileServer');

        // Call the service to process the encoded path.
        try {
            $result = $fileServer->serve($encodedPath);
        } catch (PageNotFoundException $e) {
            // Let the exception be handled globally or rethrow it.
            throw $e;
        }

        // Use CodeIgniter’s response object to serve the file.
        return $this->response
            ->setHeader('Content-Type', $result['content_type'])
            ->setBody($result['body']);
    }
}
