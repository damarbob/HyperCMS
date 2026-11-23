<?php

namespace Media\Controllers;

use App\Data\Files;
use App\Data\MediaException;
use App\Modules\ModuleController;
use CodeIgniter\HTTP\ResponseInterface;
use Media\Libraries\Browser;

/**
 * The library screen and the picker that other pages open. Both draw the same part of the page, `partials/browser`, as a screen or as
 * the dialog of the picker, and every action answers with that part as it looks afterwards, so the
 * person stays in the folder and the search they were in.
 */
class Library extends ModuleController
{
    private ?Browser $browser = null;

    private function browser(): Browser
    {
        return $this->browser ??= new Browser(service('files'));
    }

    public function index()
    {
        $data = $this->browser()->build($this->request->getGet(), 'library');

        // A swap of the library gets the library alone. The browser's own restore of a page after Back needs the whole page.
        if ($this->request->hasHeader('HX-Request') && ! $this->request->hasHeader('HX-History-Restore-Request') && $this->request->getHeaderLine('HX-Target') === 'library') {
            return view('Media\Views\partials\browser', $data);
        }

        return $this->page('Media\Views\library', $data + [
            'title'  => lang('Media.moduleName'),
            'crumbs' => [[lang('Media.moduleName'), null]],
        ]);
    }

    public function picker()
    {
        return view('Media\Views\partials\browser', $this->browser()->build($this->request->getGet(), 'picker'));
    }

    /**
     * The library as a page for a frame. It offers images, several at a time, and tells the opening page which of them were
     * chosen. The code of the opening page is kept as the digits and letters of hexadecimal, so it cannot carry markup.
     */
    public function frame()
    {
        $requester = preg_match('/^[0-9a-f]{1,256}$/', (string) $this->request->getGet('requester_id')) === 1 ? (string) $this->request->getGet('requester_id') : '';
        $data      = $this->browser()->build(['accept' => 'image', 'multiple' => '1'] + $this->request->getGet(), 'picker');

        return view('Media\Views\frame', $data + ['requester' => $requester]);
    }

    /**
     * Take the files of a multipart request into a folder. The answer is JSON, because the page sends the files one request
     * at a time from a script and shows its own progress.
     */
    public function upload(): ResponseInterface
    {
        $folder   = (string) $this->request->getPost('folder');
        $stored   = [];
        $problems = [];
        $browser  = $this->browser();

        foreach ($this->request->getFileMultiple('files') ?: [] as $file) {
            if (! $file->isValid()) {
                $problems[] = $file->getClientName() . ' ' . $file->getErrorString();

                continue;
            }

            try {
                $stored[] = $browser->present(service('files')->store($file->getTempName(), $file->getClientName(), $folder, (int) auth()->id() ?: null));
            } catch (MediaException $e) {
                $problems[] = $e->getMessage();
            }
        }

        return $this->json(['files' => $stored, 'problems' => $problems]);
    }

    public function createFolder()
    {
        $post = $this->request->getPost();
        try {
            $path = service('files')->createFolder((string) ($post['folder'] ?? ''), (string) ($post['new_folder'] ?? ''));

            return $this->answer($post, lang('Media.folderCreated', ['name' => basename($path)]), 'ok');
        } catch (MediaException $e) {
            return $this->answer($post, $e->getMessage(), 'bad');
        }
    }

    public function renameFolder()
    {
        $post = $this->request->getPost();
        try {
            $path = service('files')->renameFolder((string) ($post['folder'] ?? ''), (string) ($post['folder_name'] ?? ''));

            return $this->answer($post, lang('Media.folderRenamed', ['name' => basename($path)]), 'ok', ['folder' => $path]);
        } catch (MediaException $e) {
            return $this->answer($post, $e->getMessage(), 'bad');
        }
    }

    public function deleteFolder()
    {
        $post   = $this->request->getPost();
        $folder = (string) ($post['folder'] ?? '');
        try {
            service('files')->deleteFolder($folder);
            $parent = dirname($folder) === '.' ? '' : dirname($folder);

            return $this->answer($post, lang('Media.folderDeleted', ['name' => basename($folder)]), 'ok', ['folder' => $parent]);
        } catch (MediaException $e) {
            return $this->answer($post, $e->getMessage(), 'bad');
        }
    }

    public function rename()
    {
        $post = $this->request->getPost();
        $ids  = $this->ids($post);
        if (count($ids) !== 1) {
            return $this->answer($post, lang('Media.pickOneFile'), 'bad');
        }

        try {
            $file = service('files')->rename($ids[0], (string) ($post['file_name'] ?? ''));

            return $this->answer($post, lang('Media.fileRenamed', ['name' => $file['name']]), 'ok');
        } catch (MediaException $e) {
            return $this->answer($post, $e->getMessage(), 'bad');
        }
    }

    public function move()
    {
        $post = $this->request->getPost();
        $ids  = $this->ids($post);
        if ($ids === []) {
            return $this->answer($post, lang('Media.nothingSelected'), 'bad');
        }

        try {
            $result  = service('files')->move($ids, (string) ($post['to'] ?? ''));
            $message = $result['moved'] === 0 ? lang('Media.movedNone') : ($result['moved'] === 1 ? lang('Media.movedOne') : lang('Media.moved', ['count' => $result['moved']]));
            if ($result['skipped'] !== []) {
                $message .= ' ' . lang('Media.movedSkipped', ['names' => implode(', ', $result['skipped'])]);
            }

            return $this->answer($post, $message, $result['skipped'] === [] ? 'ok' : 'warn');
        } catch (MediaException $e) {
            return $this->answer($post, $e->getMessage(), 'bad');
        }
    }

    public function delete()
    {
        $post = $this->request->getPost();
        $ids  = $this->ids($post);
        if ($ids === []) {
            return $this->answer($post, lang('Media.nothingSelected'), 'bad');
        }

        $count = service('files')->delete($ids);

        return $this->answer($post, $count === 0 ? lang('Media.deletedNone') : ($count === 1 ? lang('Media.deletedOne') : lang('Media.deleted', ['count' => $count])), $count === 0 ? 'warn' : 'ok');
    }

    /**
     * The ticked files of a form.
     *
     * @param array<string, mixed> $post
     *
     * @return list<int>
     */
    private function ids(array $post): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) ($post['ids'] ?? [])), static fn (int $id): bool => $id > 0)));
    }

    /**
     * The library as it looks after an action, with a toast about what happened.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $override Values of the state that the action changed, such as the folder it made.
     */
    private function answer(array $post, string $message, string $type, array $override = []): ResponseInterface
    {
        $mode = ($post['mode'] ?? '') === 'picker' ? 'picker' : 'library';
        $data = $this->browser()->build(array_merge($post, $override), $mode);

        return $this->response
            ->setHeader('HX-Trigger', json_encode(['hyper:toast' => ['type' => $type, 'message' => $message]], JSON_UNESCAPED_UNICODE))
            ->setBody(view('Media\Views\partials\browser', $data));
    }
}
