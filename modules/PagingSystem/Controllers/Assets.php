<?php

namespace PagingSystem\Controllers;

use App\Data\Files;
use App\Data\MediaException;
use App\Modules\ModuleController;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Takes the images that the page editor uploads into the media library, and answers with their links, which is what the
 * asset manager of the editor expects.
 */
class Assets extends ModuleController
{
    public function assetsUpload()
    {
        $links  = [];
        $errors = [];

        foreach ($this->uploaded() as $file) {
            if (! $file->isValid() || $file->hasMoved()) {
                $errors[] = $file->getErrorString();

                continue;
            }

            try {
                $links[] = Files::url(service('files')->store($file->getTempName(), $file->getClientName(), '', (int) auth()->id() ?: null));
            } catch (MediaException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return $this->json(['status' => 'error', 'message' => implode(' ', $errors)]);
        }

        return $this->json(['status' => 'success', 'message' => $links, 'data' => $links]);
    }

    /**
     * Every file of the request, whatever the name of its input.
     *
     * @return list<UploadedFile>
     */
    private function uploaded(): array
    {
        $found = [];
        foreach ($this->request->getFiles() as $group) {
            foreach (is_array($group) ? $group : [$group] as $file) {
                if ($file instanceof UploadedFile) {
                    $found[] = $file;
                }
            }
        }

        return $found;
    }
}
