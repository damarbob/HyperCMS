<?php

namespace App\Data;

use CodeIgniter\Database\BaseConnection;
use Config\Media as MediaConfig;
use Config\Mimes;
use Throwable;

/**
 * The media library: the files, and the folders they sit in.
 *
 * A file is a row of `hyper_files` and a file in the upload folder at the row's `path`. Folders are real folders of the
 * upload folder, so an empty folder exists without a row. Everything that touches the disk goes through this class,
 * which holds three rules. A name must pass `is_safe_file_name()`, so nothing that a server could run is ever stored. A
 * folder is a plain relative path with no `..`, so nothing leaves the upload folder. The upload folder must not be
 * inside the public folder, so the web server never serves a file by itself.
 */
final class Files
{
    public const KINDS = ['any', 'image', 'video', 'audio', 'document'];

    private const MAX_NAME  = 200;
    private const MAX_DEPTH = 8;

    /**
     * Rows already read, by id, so a list that shows one file twice asks once.
     *
     * @var array<int, array<string, mixed>|null>
     */
    private array $memo = [];

    private ?string $root = null;

    public function __construct(private readonly ?BaseConnection $connection = null, private readonly ?string $uploadPath = null)
    {
        helper('hyper_directory');
    }

    private function db(): BaseConnection
    {
        return $this->connection ?? db_connect();
    }

    /**
     * The upload folder, made when it is missing.
     *
     * @throws MediaException when it cannot be made, or lies inside the public folder.
     */
    public function root(): string
    {
        if ($this->root !== null) {
            return $this->root;
        }

        $path = $this->uploadPath ?? config(MediaConfig::class)->uploadPath;
        if ($path === '') {
            throw new MediaException(lang('Files.rootMissing'));
        }
        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new MediaException(lang('Files.rootCannotBeMade', ['path' => $path]));
        }

        $real   = realpath($path);
        $public = realpath(FCPATH);
        if ($real === false) {
            throw new MediaException(lang('Files.rootCannotBeMade', ['path' => $path]));
        }
        if ($public !== false && ($real === $public || str_starts_with($real . DIRECTORY_SEPARATOR, $public . DIRECTORY_SEPARATOR))) {
            throw new MediaException(lang('Files.rootIsPublic'));
        }

        return $this->root = $real;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Names and paths
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * A folder as a clean relative path of safe names joined by `/`, or an empty string for the top.
     *
     * @throws MediaException when a part is not a safe name, or the folder is too deep.
     */
    public static function cleanFolder(string $folder): string
    {
        helper('hyper_directory');

        $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $folder)), static fn (string $p): bool => $p !== ''));
        if (count($parts) > self::MAX_DEPTH) {
            throw new MediaException(lang('Files.folderTooDeep'));
        }
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..' || ! is_safe_file_name($part) || mb_strlen($part) > self::MAX_NAME) {
                throw new MediaException(lang('Files.badFolderName', ['name' => $part]));
            }
        }

        return implode('/', $parts);
    }

    /**
     * A name that came from outside, made fit for a file, or an error when it is one the library refuses.
     *
     * @throws MediaException
     */
    public static function cleanName(string $name): string
    {
        helper('hyper_directory');

        $name = trim(basename(str_replace('\\', '/', $name)));
        $name = trim(preg_replace('/[\x00-\x1f\x7f\/\\\\:*?"<>|]+/u', '-', $name) ?? '');
        if ($name === '' || mb_strlen($name) > self::MAX_NAME) {
            throw new MediaException(lang('Files.badFileName', ['name' => $name]));
        }
        if (! is_safe_file_name($name)) {
            throw new MediaException(lang('Files.fileNameRefused', ['name' => $name]));
        }

        return $name;
    }

    /**
     * The name with `-1`, `-2` and so on before the extension, until `$taken` says it is free.
     *
     * @param callable(string): bool $taken
     */
    public static function freeName(string $name, callable $taken): string
    {
        if (! $taken($name)) {
            return $name;
        }

        for ($i = 1; $i < 10000; $i++) {
            $try = self::suffixed($name, $i);
            if (! $taken($try)) {
                return $try;
            }
        }

        throw new MediaException(lang('Files.badFileName', ['name' => $name]));
    }

    private static function suffixed(string $name, int $number): string
    {
        $dot = strrpos($name, '.');

        return $dot === false || $dot === 0 ? $name . '-' . $number : substr($name, 0, $dot) . '-' . $number . substr($name, $dot);
    }

    /**
     * The path of a file below the site, such as `media/12/photo.png`. The name is only there for readers and for the
     * download, and the file is found by its id.
     *
     * @param array<string, mixed> $file
     */
    public static function path(array $file): string
    {
        return 'media/' . (int) $file['id'] . '/' . rawurlencode((string) $file['name']);
    }

    /**
     * The address of a file for a page of the admin.
     *
     * @param array<string, mixed> $file
     */
    public static function url(array $file): string
    {
        return base_url(self::path($file));
    }

    /**
     * Which of the kinds of `KINDS` a type belongs to, for the picker's filter.
     */
    public static function kindOf(string $mime): string
    {
        $mime = strtolower($mime);
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if ($mime === 'application/pdf' || str_starts_with($mime, 'text/') || str_contains($mime, 'officedocument') || str_contains($mime, 'msword') || str_contains($mime, 'ms-excel') || str_contains($mime, 'ms-powerpoint') || $mime === 'application/rtf') {
            return 'document';
        }

        return 'other';
    }

    public static function accepts(string $mime, string $accept): bool
    {
        return $accept === 'any' || $accept === '' || self::kindOf($mime) === $accept;
    }

    /**
     * Whether a browser may show a file in the page, as opposed to offering it as a download.
     */
    public static function isInline(string $mime): bool
    {
        $mime = strtolower($mime);

        return in_array(self::kindOf($mime), ['image', 'video', 'audio'], true) || $mime === 'application/pdf' || $mime === 'text/plain';
    }

    /**
     * The type of a file from what is in it, and from its name when the content says too little.
     */
    public static function mimeOf(string $absolute, string $name): string
    {
        $found = '';
        if (class_exists('finfo')) {
            try {
                $found = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
            } catch (Throwable) {
                $found = '';
            }
        }

        $byName = Mimes::guessTypeFromExtension(strtolower(pathinfo($name, PATHINFO_EXTENSION))) ?? '';
        if ($found === '' || in_array($found, ['text/plain', 'application/octet-stream', 'application/xml', 'text/xml', 'application/zip', 'text/html'], true)) {
            // The content of a text file or a zip says little, so the name decides, and a name that says nothing leaves what was found.
            if ($byName !== '' && ! ($found === 'text/html' && $byName !== 'text/html')) {
                return $byName;
            }
        }

        return $found !== '' ? $found : ($byName !== '' ? $byName : 'application/octet-stream');
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Reading
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        if (! array_key_exists($id, $this->memo)) {
            $this->memo[$id] = $this->db()->table('hyper_files')->where('id', $id)->get()->getRowArray() ?: null;
        }

        return $this->memo[$id];
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>> The rows that exist, by id.
     */
    public function findMany(array $ids): array
    {
        $ids  = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        $need = array_values(array_filter($ids, fn (int $id): bool => ! array_key_exists($id, $this->memo)));
        if ($need !== []) {
            $found = [];
            foreach ($this->db()->table('hyper_files')->whereIn('id', $need)->get()->getResultArray() as $row) {
                $found[(int) $row['id']] = $row;
            }
            foreach ($need as $id) {
                $this->memo[$id] = $found[$id] ?? null;
            }
        }

        $rows = [];
        foreach ($ids as $id) {
            if ($this->memo[$id] !== null) {
                $rows[$id] = $this->memo[$id];
            }
        }

        return $rows;
    }

    /**
     * The place of a file on the disk, or null when the row has no file or the file is not inside the upload folder.
     *
     * @param array<string, mixed> $file
     */
    public function absolute(array $file): ?string
    {
        $full = realpath($this->root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $file['path']));
        if ($full === false || ! is_file($full) || ! str_starts_with($full, $this->root() . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $full;
    }

    /**
     * The files of one folder, or the files of the whole library whose name holds the search text.
     *
     * @param 'new'|'name' $sort
     *
     * @return array{files: list<array<string, mixed>>, total: int}
     */
    public function list(string $folder = '', string $search = '', string $kind = 'any', int $page = 1, int $perPage = 48, string $sort = 'new'): array
    {
        $build = function () use ($folder, $search, $kind): \CodeIgniter\Database\BaseBuilder {
            $b  = $this->db()->table('hyper_files');
            $db = $this->db();

            if (trim($search) !== '') {
                $b->where("name COLLATE utf8mb4_general_ci LIKE '%" . $db->escapeLikeString(trim($search)) . "%' ESCAPE '!'", null, false);
            } elseif ($folder === '') {
                $b->where("path NOT LIKE '%/%'", null, false);
            } else {
                $like = $db->escapeLikeString($folder);
                $b->where("path LIKE '{$like}/%' ESCAPE '!'", null, false)->where("path NOT LIKE '{$like}/%/%' ESCAPE '!'", null, false);
            }

            match ($kind) {
                'image', 'video', 'audio' => $b->like('mime', $kind . '/', 'after'),
                'document' => $b->groupStart()->like('mime', 'text/', 'after')->orLike('mime', 'pdf')->orLike('mime', 'officedocument')->orLike('mime', 'msword')->orLike('mime', 'ms-excel')->orLike('mime', 'ms-powerpoint')->orWhere('mime', 'application/rtf')->groupEnd(),
                default => null,
            };

            return $b;
        };

        $total = (int) $build()->countAllResults();
        $page  = max(1, $page);
        $b     = $build();
        $sort === 'name' ? $b->orderBy('name', 'ASC') : $b->orderBy('created_at', 'DESC')->orderBy('id', 'DESC');
        $rows  = $b->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        foreach ($rows as $row) {
            $this->memo[(int) $row['id']] = $row;
        }

        return ['files' => $rows, 'total' => $total];
    }

    /**
     * The folders directly inside a folder, by name.
     *
     * @return list<string>
     */
    public function subfolders(string $folder = ''): array
    {
        $folder = self::cleanFolder($folder);
        $dir    = $this->folderPlace($folder);
        $names  = [];
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($dir . DIRECTORY_SEPARATOR . $item) && ! is_link($dir . DIRECTORY_SEPARATOR . $item)) {
                $names[] = $item;
            }
        }
        natcasesort($names);

        return array_values($names);
    }

    /**
     * Every folder of the library as a path, for the choice of a place to move files to.
     *
     * @return list<string>
     */
    public function allFolders(string $below = '', int $depth = 0): array
    {
        $found = [];
        foreach ($this->subfolders($below) as $name) {
            $path    = ltrim($below . '/' . $name, '/');
            $found[] = $path;
            if ($depth < self::MAX_DEPTH) {
                array_push($found, ...$this->allFolders($path, $depth + 1));
            }
        }

        return $found;
    }

    private function folderPlace(string $folder): string
    {
        return $folder === '' ? $this->root() : $this->root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Changing
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Take a file that was uploaded, or any file on the disk, into a folder of the library.
     *
     * @return array<string, mixed> The row.
     *
     * @throws MediaException
     */
    public function store(string $source, string $name, string $folder = '', ?int $uploaderId = null, bool $uploaded = true): array
    {
        $name   = self::cleanName($name);
        $folder = self::cleanFolder($folder);
        $dir    = $this->folderPlace($folder);
        if (! is_dir($dir)) {
            throw new MediaException(lang('Files.folderNotFound', ['name' => $folder]));
        }
        if (! is_file($source)) {
            throw new MediaException(lang('Files.sourceMissing', ['name' => $name]));
        }

        $name = self::freeName($name, fn (string $try): bool => $this->taken($folder, $try));
        $path = ltrim($folder . '/' . $name, '/');
        $to   = $dir . DIRECTORY_SEPARATOR . $name;

        $ok = $uploaded ? move_uploaded_file($source, $to) : copy($source, $to);
        if (! $ok) {
            throw new MediaException(lang('Files.cannotWrite', ['name' => $name]));
        }
        @chmod($to, 0644);

        return $this->record($path, $name, $to, $uploaderId);
    }

    /**
     * Take a file of the disk into the library at an exact path, or find the row that already holds the same file.
     * The upgrade uses it, and running it twice for one file gives one row.
     *
     * @return array<string, mixed>
     *
     * @throws MediaException
     */
    public function import(string $source, string $path, ?int $uploaderId = null): array
    {
        $path   = ltrim(str_replace('\\', '/', $path), '/');
        $folder = self::cleanFolder(dirname($path) === '.' ? '' : dirname($path));
        $name   = self::cleanName(basename($path));
        if (! is_file($source)) {
            throw new MediaException(lang('Files.sourceMissing', ['name' => $name]));
        }

        $this->makeFolder($folder);
        $hash = md5_file($source);
        for ($i = 0; $i < 10000; $i++) {
            $try = $i === 0 ? $name : self::suffixed($name, $i);
            $tryPath = ltrim($folder . '/' . $try, '/');
            $row     = $this->db()->table('hyper_files')->where('path', $tryPath)->get()->getRowArray();
            $to      = $this->folderPlace($folder) . DIRECTORY_SEPARATOR . $try;

            if ($row !== null && $row !== []) {
                if (is_file($to) && md5_file($to) === $hash) {
                    return $row;
                }
                continue;
            }
            if (is_file($to)) {
                if (md5_file($to) === $hash) {
                    return $this->record($tryPath, $try, $to, $uploaderId);
                }
                continue;
            }
            if (! copy($source, $to)) {
                throw new MediaException(lang('Files.cannotWrite', ['name' => $try]));
            }
            @chmod($to, 0644);

            return $this->record($tryPath, $try, $to, $uploaderId);
        }

        throw new MediaException(lang('Files.cannotWrite', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function record(string $path, string $name, string $absolute, ?int $uploaderId): array
    {
        $row = [
            'path'        => $path,
            'name'        => $name,
            'mime'        => self::mimeOf($absolute, $name),
            'size'        => (int) filesize($absolute),
            'uploader_id' => $uploaderId,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        $this->db()->table('hyper_files')->insert($row);
        $row['id']              = (int) $this->db()->insertID();
        $this->memo[$row['id']] = $row;

        return $row;
    }

    private function taken(string $folder, string $name): bool
    {
        $path = ltrim($folder . '/' . $name, '/');

        return file_exists($this->folderPlace($folder) . DIRECTORY_SEPARATOR . $name)
            || $this->db()->table('hyper_files')->where('path', $path)->countAllResults() > 0;
    }

    /**
     * Give a file another name. It stays in its folder, and its id and so every link to it stay as they are.
     *
     * @return array<string, mixed>
     *
     * @throws MediaException
     */
    public function rename(int $id, string $name): array
    {
        $file = $this->find($id) ?? throw new MediaException(lang('Files.fileNotFound'));
        $name = self::cleanName($name);
        if ($name === $file['name']) {
            return $file;
        }

        $folder = dirname((string) $file['path']) === '.' ? '' : dirname((string) $file['path']);
        if ($this->taken($folder, $name)) {
            throw new MediaException(lang('Files.nameTaken', ['name' => $name]));
        }

        return $this->relocate($file, $folder, $name);
    }

    /**
     * Move files to another folder. A file whose name is taken there stays where it is and is listed.
     *
     * @param list<int> $ids
     *
     * @return array{moved: int, skipped: list<string>}
     *
     * @throws MediaException
     */
    public function move(array $ids, string $folder): array
    {
        $folder = self::cleanFolder($folder);
        if (! is_dir($this->folderPlace($folder))) {
            throw new MediaException(lang('Files.folderNotFound', ['name' => $folder]));
        }

        $moved   = 0;
        $skipped = [];
        foreach ($this->findMany($ids) as $file) {
            $from = dirname((string) $file['path']) === '.' ? '' : dirname((string) $file['path']);
            if ($from === $folder) {
                continue;
            }
            if ($this->taken($folder, (string) $file['name'])) {
                $skipped[] = (string) $file['name'];

                continue;
            }
            $this->relocate($file, $folder, (string) $file['name']);
            $moved++;
        }

        return ['moved' => $moved, 'skipped' => $skipped];
    }

    /**
     * @param array<string, mixed> $file
     *
     * @return array<string, mixed>
     */
    private function relocate(array $file, string $folder, string $name): array
    {
        $from = $this->absolute($file);
        if ($from === null) {
            throw new MediaException(lang('Files.fileMissingOnDisk', ['name' => $file['name']]));
        }

        $path = ltrim($folder . '/' . $name, '/');
        if (! @rename($from, $this->folderPlace($folder) . DIRECTORY_SEPARATOR . $name)) {
            throw new MediaException(lang('Files.cannotWrite', ['name' => $name]));
        }

        $this->db()->table('hyper_files')->where('id', (int) $file['id'])->update(['path' => $path, 'name' => $name]);
        $file['path'] = $path;
        $file['name'] = $name;

        return $this->memo[(int) $file['id']] = $file;
    }

    /**
     * Remove files from the disk and the library. Places that still hold their id show a missing file.
     *
     * @param list<int> $ids
     *
     * @return int How many were removed.
     */
    public function delete(array $ids): int
    {
        $count = 0;
        foreach ($this->findMany($ids) as $file) {
            $full = $this->absolute($file);
            if ($full !== null && ! @unlink($full)) {
                continue;
            }
            $this->db()->table('hyper_files')->where('id', (int) $file['id'])->delete();
            $this->memo[(int) $file['id']] = null;
            $count++;
        }

        return $count;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Folders
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Make a folder, and the ones above it that are missing.
     *
     * @throws MediaException
     */
    public function makeFolder(string $folder): string
    {
        $folder = self::cleanFolder($folder);
        $dir    = $this->folderPlace($folder);
        if ($folder !== '' && ! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new MediaException(lang('Files.cannotWrite', ['name' => $folder]));
        }

        return $folder;
    }

    /**
     * @throws MediaException
     */
    public function createFolder(string $parent, string $name): string
    {
        $parent = self::cleanFolder($parent);
        $name   = self::cleanFolder($name);
        if ($name === '' || str_contains($name, '/')) {
            throw new MediaException(lang('Files.badFolderName', ['name' => $name]));
        }
        if (! is_dir($this->folderPlace($parent))) {
            throw new MediaException(lang('Files.folderNotFound', ['name' => $parent]));
        }

        $path = ltrim($parent . '/' . $name, '/');
        if (file_exists($this->folderPlace($path))) {
            throw new MediaException(lang('Files.nameTaken', ['name' => $name]));
        }

        return $this->makeFolder($path);
    }

    /**
     * Give a folder another name. The files in it keep their ids and so their links.
     *
     * @throws MediaException
     */
    public function renameFolder(string $folder, string $name): string
    {
        $folder = self::cleanFolder($folder);
        $name   = self::cleanFolder($name);
        if ($folder === '' || $name === '' || str_contains($name, '/')) {
            throw new MediaException(lang('Files.badFolderName', ['name' => $name]));
        }
        if (! is_dir($this->folderPlace($folder))) {
            throw new MediaException(lang('Files.folderNotFound', ['name' => $folder]));
        }

        $parent = dirname($folder) === '.' ? '' : dirname($folder);
        $new    = ltrim($parent . '/' . $name, '/');
        if ($new === $folder) {
            return $folder;
        }
        if (file_exists($this->folderPlace($new))) {
            throw new MediaException(lang('Files.nameTaken', ['name' => $name]));
        }
        if (! @rename($this->folderPlace($folder), $this->folderPlace($new))) {
            throw new MediaException(lang('Files.cannotWrite', ['name' => $name]));
        }

        $like = $this->db()->escapeLikeString($folder);
        $this->db()->query(
            'UPDATE ' . $this->db()->prefixTable('hyper_files') . " SET path = CONCAT(?, SUBSTRING(path, ?)) WHERE path LIKE '{$like}/%' ESCAPE '!'",
            [$new, strlen($folder) + 1]
        );
        $this->memo = [];

        return $new;
    }

    /**
     * Remove a folder that has nothing in it.
     *
     * @throws MediaException
     */
    public function deleteFolder(string $folder): void
    {
        $folder = self::cleanFolder($folder);
        $dir    = $this->folderPlace($folder);
        if ($folder === '' || ! is_dir($dir)) {
            throw new MediaException(lang('Files.folderNotFound', ['name' => $folder]));
        }
        if ((scandir($dir) ?: []) !== ['.', '..'] || ! @rmdir($dir)) {
            throw new MediaException(lang('Files.folderNotEmpty', ['name' => $folder]));
        }
    }
}
