<?php

namespace App\Libraries\Upgrade\Steps;

use App\Data\Entries\EntryEngine;
use App\Data\Files;
use App\Data\MediaException;
use App\Data\WriteVersions;
use App\Libraries\Upgrade\Convert\FileLinks;
use App\Libraries\Upgrade\ConversionStep;
use App\Libraries\Upgrade\Payload;
use App\Libraries\Upgrade\SchemaStore;
use CodeIgniter\Database\BaseConnection;

/**
 * Step 5. Moves the files of the site into the media library and rewrites every stored link to them.
 *
 * Every file of the old upload folder, `uploads/` in the public folder, is copied into the library, and so is any other
 * file of the public folder that an entry points at. The copies keep their folder, below the library, and the originals are
 * left where they were, because the upgrade never deletes. Then each entry and each saved version of it is read: a `file`
 * field gets the ids of the library files in place of paths and links, and every old link in the text of any other field,
 * rich text, page code or the project data of the page editor, becomes `/media/{id}/{name}`.
 *
 * A file that is named twice is copied once, because the library finds a file it already holds by its content. A link to a
 * file that is not there, or that the library refuses, is left as it is and listed. The entries go in id order and the step
 * remembers the last chunk it finished.
 */
final class ConvertFiles implements ConversionStep
{
    private const CHUNK = 200;

    /**
     * The definition of each model by id, read once.
     *
     * @var array<int, list<array<string, mixed>>>|null
     */
    private ?array $definitions = null;

    /**
     * The files found by path, so each is copied once whatever the number of links to it. Null is a path with no file.
     *
     * @var array<string, array{id: int, name: string}|null>
     */
    private array $found = [];

    /**
     * Paths that the library refused, with the reason, for the report.
     *
     * @var array<string, string>
     */
    private array $refused = [];

    private int $copied = 0;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly FileLinks $links,
        private readonly EntryEngine $engine,
        private readonly WriteVersions $versions,
        private readonly Files $files,
        private readonly string $publicRoot,
    ) {
    }

    public function number(): int
    {
        return 5;
    }

    public function name(): string
    {
        return 'Move the files into the media library and rewrite their links';
    }

    public function run(SchemaStore $store, bool $dryRun, ?float $deadline = null): array
    {
        $changed  = 0;
        $problems = [];
        $touched  = [];

        $this->importUploads($dryRun, $problems);

        $cursor = $store->cursor($this->number()) ?? 0;
        while (true) {
            $entries = $this->db->query(
                'SELECT id, model_id, sd_entry_id, deleted_at FROM ' . $this->db->prefixTable('hyper_entries') . ' WHERE id > ? ORDER BY id LIMIT ' . self::CHUNK,
                [$cursor]
            )->getResultArray();
            if ($entries === []) {
                break;
            }

            foreach ($entries as $entry) {
                $id          = (int) $entry['id'];
                $definitions = $this->definitions()[(int) $entry['model_id']] ?? null;
                $cursor      = $id;
                if ($definitions === null) {
                    continue;
                }

                $did = $this->convertLive($entry, $definitions, $dryRun, $problems) | $this->convertRevisions($id, $definitions, $dryRun, $problems);
                if ($did) {
                    $changed++;
                    $touched[(int) $entry['model_id']] = true;
                }
            }

            if (! $dryRun) {
                $store->setCursor($this->number(), $cursor);
            }

            if (! $dryRun && $deadline !== null && microtime(true) >= $deadline) {
                if ($touched !== []) {
                    $this->versions->bump(array_keys($touched));
                }

                return ['changed' => $changed, 'problems' => $problems, 'complete' => false];
            }
        }

        if (! $dryRun && $touched !== []) {
            $this->versions->bump(array_keys($touched));
        }

        foreach ($this->refused as $path => $why) {
            $problems[] = "The file $path was not copied. $why";
        }
        array_unshift($problems, $dryRun
            ? 'Files that would be copied into the media library: ' . $this->copied . '.'
            : 'Files copied into the media library, or found there already: ' . $this->copied . '. The originals are left where they were.');

        return ['changed' => $changed, 'problems' => $problems];
    }

    /**
     * Copy every file of the old upload folder into the library, so the library holds what the site had.
     *
     * @param list<string> $problems
     */
    private function importUploads(bool $dryRun, array &$problems): void
    {
        $root = realpath($this->publicRoot . DIRECTORY_SEPARATOR . 'uploads');
        if ($root === false || ! is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $file) {
            // Dot files and the empty `index.html` that a folder may hold are not files of the site.
            if (! $file->isFile() || $file->isLink() || $file->getFilename()[0] === '.' || $file->getFilename() === 'index.html') {
                continue;
            }
            $relative = 'uploads/' . ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            $this->resolve($relative, $dryRun);
        }
    }

    /**
     * The library file for a path relative to the public folder, copying the file in when it is not there yet. In a dry run
     * nothing is copied, and a file that exists answers with id 0.
     *
     * @return array{id: int, name: string}|null
     */
    private function resolve(string $path, bool $dryRun): ?array
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (array_key_exists($path, $this->found)) {
            return $this->found[$path];
        }

        // A link made when the file manager's root was the project folder starts with `public/`.
        $source = null;
        foreach ([$path, preg_replace('#^public/#', '', $path)] as $candidate) {
            $full = realpath($this->publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $candidate));
            if ($full !== false && is_file($full) && str_starts_with($full, rtrim($this->publicRoot, '/\\') . DIRECTORY_SEPARATOR)) {
                $source = [$full, ltrim(str_replace('\\', '/', substr($full, strlen(rtrim($this->publicRoot, '/\\')))), '/')];

                break;
            }
        }
        if ($source === null) {
            return $this->found[$path] = null;
        }

        // The upload folder is the top of the library, and any other path keeps its folders below it.
        $target = str_starts_with($source[1], 'uploads/') ? substr($source[1], 8) : $source[1];

        try {
            helper('hyper_directory');
            if (! is_safe_archive_entry($target)) {
                throw new MediaException(lang('Files.fileNameRefused', ['name' => basename($target)]));
            }
            if ($dryRun) {
                $this->copied++;

                return $this->found[$path] = ['id' => 0, 'name' => basename($target)];
            }
            $row = $this->files->import($source[0], $target);
            $this->copied++;

            return $this->found[$path] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
        } catch (MediaException $e) {
            $this->refused[$path] = $e->getMessage();

            return $this->found[$path] = null;
        }
    }

    /**
     * @param array<string, mixed>       $entry
     * @param list<array<string, mixed>> $definitions
     * @param list<string>               $problems
     */
    private function convertLive(array $entry, array $definitions, bool $dryRun, array &$problems): bool
    {
        if ($entry['deleted_at'] !== null || $entry['sd_entry_id'] === null) {
            return false;
        }

        $live = $this->engine->get((int) $entry['sd_entry_id']);
        if ($live === null) {
            return false;
        }

        $result = $this->links->convert($live, $definitions, fn (string $path): ?array => $this->resolve($path, $dryRun));
        foreach ($result['problems'] as $problem) {
            $problems[] = "Entry {$entry['id']}, field {$problem['field']}: {$problem['problem']}";
        }
        if (json_encode($result['fields']) === json_encode($live)) {
            return false;
        }

        if (! $dryRun) {
            $this->engine->update((int) $entry['sd_entry_id'], $result['fields']);
            $this->db->table('hyper_entries')->where('id', (int) $entry['id'])->update(['payload_bytes' => strlen(Payload::encode((object) $result['fields']))]);
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @param list<string>               $problems
     */
    private function convertRevisions(int $entryId, array $definitions, bool $dryRun, array &$problems): bool
    {
        $any       = false;
        $revisions = $this->db->table('hyper_entry_revisions')->select('id, fields')->where('entry_id', $entryId)->where('fields IS NOT NULL', null, false)->get()->getResultArray();

        foreach ($revisions as $revision) {
            $old = json_decode((string) $revision['fields'], true);
            if (! is_array($old) || ($old !== [] && array_is_list($old))) {
                continue;
            }

            $result = $this->links->convert($old, $definitions, fn (string $path): ?array => $this->resolve($path, $dryRun));
            foreach ($result['problems'] as $problem) {
                $problems[] = "Entry $entryId, version {$revision['id']}, field {$problem['field']}: {$problem['problem']}";
            }
            if (json_encode($result['fields']) === json_encode($old)) {
                continue;
            }

            $any = true;
            if (! $dryRun) {
                $this->db->table('hyper_entry_revisions')->where('id', (int) $revision['id'])->update(['fields' => Payload::encode((object) $result['fields'])]);
            }
        }

        return $any;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function definitions(): array
    {
        if ($this->definitions === null) {
            $this->definitions = [];
            foreach ($this->db->table('hyper_models')->select('id, fields')->get()->getResultArray() as $row) {
                $fields = json_decode((string) $row['fields'], true);
                $this->definitions[(int) $row['id']] = is_array($fields) ? array_values(array_filter($fields, 'is_array')) : [];
            }
        }

        return $this->definitions;
    }
}
