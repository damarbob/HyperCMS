<?php

namespace Media\Libraries;

use App\Data\Files;
use App\Data\MediaException;

/**
 * Reads what the library screen and the picker draw: the folder the person is in, the files and folders in it or the files
 * that match a search, and the links between them.
 *
 * The state is a handful of names that travel in the query string and in every form of the page, so each action can answer
 * with the same view of the library that the person was looking at.
 */
final class Browser
{
    public const LIBRARY_PAGE = 48;
    public const PICKER_PAGE  = 24;

    public function __construct(private readonly Files $files)
    {
        helper('number');
    }

    /**
     * @param array<string, mixed> $input The query string or the posted values of a form that carries the same names.
     * @param 'library'|'picker'   $mode
     *
     * @return array<string, mixed>
     */
    public function build(array $input, string $mode): array
    {
        $folder = '';
        try {
            $folder = Files::cleanFolder((string) ($input['folder'] ?? ''));
        } catch (MediaException) {
            $folder = '';
        }
        if ($folder !== '' && ! in_array($folder, $this->files->allFolders(), true)) {
            $folder = '';
        }

        $accept = in_array($input['accept'] ?? 'any', Files::KINDS, true) ? (string) ($input['accept'] ?? 'any') : 'any';
        $kind   = in_array($input['kind'] ?? 'any', Files::KINDS, true) ? (string) ($input['kind'] ?? 'any') : 'any';
        if ($accept !== 'any') {
            $kind = $accept;
        }

        $state = [
            'folder'   => $folder,
            'q'        => trim((string) ($input['q'] ?? '')),
            'kind'     => $kind,
            'sort'     => ($input['sort'] ?? 'new') === 'name' ? 'name' : 'new',
            'page'     => max(1, (int) ($input['page'] ?? 1)),
            'accept'   => $accept,
            'multiple' => ! empty($input['multiple']) && $input['multiple'] !== '0',
        ];

        $per    = $mode === 'picker' ? self::PICKER_PAGE : self::LIBRARY_PAGE;
        $result = $this->files->list($state['folder'], $state['q'], $state['kind'], $state['page'], $per, $state['sort']);

        // A page past the last one shows the last one.
        $last = max(1, (int) ceil($result['total'] / $per));
        if ($state['page'] > $last) {
            $state['page'] = $last;
            $result        = $this->files->list($state['folder'], $state['q'], $state['kind'], $state['page'], $per, $state['sort']);
        }

        $base = base_url($mode === 'picker' ? 'admin/media/picker' : 'admin/media');
        $link = static function (array $override = []) use ($state, $base): string {
            $want = array_merge($state, $override);
            if (array_key_exists('folder', $override) || array_key_exists('q', $override) || array_key_exists('kind', $override)) {
                $want['page'] = $override['page'] ?? 1;
            }
            $query = array_filter([
                'folder'   => $want['folder'],
                'q'        => $want['q'],
                'kind'     => $want['accept'] === 'any' && $want['kind'] !== 'any' ? $want['kind'] : '',
                'sort'     => $want['sort'] !== 'new' ? $want['sort'] : '',
                'page'     => $want['page'] > 1 ? $want['page'] : '',
                'accept'   => $want['accept'] !== 'any' ? $want['accept'] : '',
                'multiple' => $want['multiple'] ? '1' : '',
            ], static fn ($v): bool => $v !== '' && $v !== null);

            return $base . ($query === [] ? '' : '?' . http_build_query($query));
        };

        $folders = [];
        if ($state['q'] === '') {
            foreach ($this->files->subfolders($state['folder']) as $name) {
                $path      = ltrim($state['folder'] . '/' . $name, '/');
                $folders[] = ['name' => $name, 'path' => $path, 'url' => $link(['folder' => $path])];
            }
        }

        $trail = [['label' => lang('Media.moduleName'), 'folder' => '', 'url' => $link(['folder' => ''])]];
        $walk   = '';
        foreach (array_filter(explode('/', $state['folder'])) as $part) {
            $walk    = ltrim($walk . '/' . $part, '/');
            $trail[] = ['label' => $part, 'folder' => $walk, 'url' => $link(['folder' => $walk])];
        }

        return [
            'mode'       => $mode,
            'state'      => $state,
            'per'        => $per,
            'total'      => $result['total'],
            'files'      => array_map($this->present(...), $result['files']),
            'folders'    => $folders,
            'trail'       => $trail,
            'link'       => $link,
            'allFolders' => $this->files->allFolders(),
            'base'       => $base,
        ];
    }

    /**
     * A row as the screen and the picker use it. The picker sends `data` back to the page that opened it.
     *
     * @param array<string, mixed> $file
     *
     * @return array<string, mixed>
     */
    public function present(array $file): array
    {
        $mime = (string) $file['mime'];

        return [
            'id'       => (int) $file['id'],
            'name'     => (string) $file['name'],
            'mime'     => $mime,
            'kind'     => Files::kindOf($mime),
            'size'     => (int) $file['size'],
            'sizeText' => number_to_size((int) $file['size'], 1),
            'url'      => Files::url($file),
            'path'     => '/' . Files::path($file),
            'folder'   => dirname((string) $file['path']) === '.' ? '' : dirname((string) $file['path']),
            'created'  => (string) ($file['created_at'] ?? ''),
        ];
    }
}
