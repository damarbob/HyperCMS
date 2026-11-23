<?php

namespace App\Libraries\Upgrade\Convert;

/**
 * Turns the old ways of pointing at a file into the id of a file of the media library, and the old links in text into
 * `/media/{id}/{name}`. It works on arrays and strings and touches neither the disk nor a database: the caller gives it a
 * function that finds the file of a path, and that function decides what finding means.
 *
 * Two old ways exist. The old upload form kept the path of the file, relative to the public folder, such as
 * `uploads/photo-1a2b.png`. The old file manager and the editors kept a link to the public file server, whatever the
 * host, with the path written in hexadecimal: `http://host/public/file-server/serve/75706c6f6164732f...`. In text that
 * is itself JSON, such as the project data of the page editor, the slashes of a link are written `\/`.
 *
 * A link that cannot be turned into a file is left as it is and listed, never guessed.
 */
final class FileLinks
{
    /**
     * One link to the old file server, with its host and any path before it, which a new link does not need. The slashes
     * may be escaped with a backslash. The hexadecimal path is the first group.
     */
    private const LINK = '~(?:https?:(?:\\\\?/){2}[^\s"\'<>()]*?)?(?:\\\\?/)public(?:\\\\?/)file-server(?:\\\\?/)serve(?:\\\\?/)([0-9A-Fa-f]+)~';

    /**
     * Rewrite every old file server link in a piece of text.
     *
     * @param callable(string): ?array{id: int, name: string} $resolve The file of a path relative to the public folder, or null.
     *
     * @return array{text: string, missing: list<string>}
     */
    public function rewriteText(string $text, callable $resolve): array
    {
        if (stripos($text, 'file-server') === false) {
            return ['text' => $text, 'missing' => []];
        }

        $missing = [];
        $out     = preg_replace_callback(self::LINK, function (array $m) use ($resolve, &$missing): string {
            $path = self::pathOf($m[1]);
            $file = $path === null ? null : $resolve($path);
            if ($file === null) {
                $missing[] = $path ?? $m[1];

                return $m[0];
            }

            $link = self::link($file);

            return str_contains($m[0], '\\/') ? str_replace('/', '\\/', $link) : $link;
        }, $text);

        return ['text' => $out ?? $text, 'missing' => array_values(array_unique($missing))];
    }

    /**
     * The value of a `file` field, from the shape the old form stored to the ids of library files. Ids that are there
     * already are kept, so the conversion can run twice.
     *
     * @param callable(string): ?array{id: int, name: string} $resolve
     *
     * @return array{value: mixed, problems: list<string>}
     */
    public function fieldValue(mixed $value, bool $multiple, callable $resolve): array
    {
        $items = $value === null || $value === '' ? [] : (is_array($value) && array_is_list($value) ? $value : [$value]);

        $out      = [];
        $problems = [];
        foreach ($items as $item) {
            if (is_int($item)) {
                $out[] = $item;

                continue;
            }
            if (is_string($item) && trim($item) === '') {
                continue;
            }
            if (! is_string($item)) {
                $out[] = $item;

                continue;
            }

            [$path, $why] = $this->pathOfItem(trim($item));
            $file         = $path === null ? null : $resolve($path);
            if ($file === null) {
                $out[]      = $item;
                $problems[] = $why ?? 'the file ' . $path . ' is not in the public folder, or could not be copied';

                continue;
            }
            $out[] = (int) $file['id'];
        }

        return ['value' => $multiple ? $out : ($out[0] ?? null), 'problems' => $problems];
    }

    /**
     * The values of one entry, or one entry version, with its file fields and its links rewritten.
     *
     * @param array<string, mixed>                            $fields      The values by field id.
     * @param list<array<string, mixed>>                      $definitions The version 2 definition of the model.
     * @param callable(string): ?array{id: int, name: string} $resolve
     *
     * @return array{fields: array<string, mixed>, problems: list<array{field: string, problem: string}>}
     */
    public function convert(array $fields, array $definitions, callable $resolve): array
    {
        $byId = [];
        foreach ($definitions as $definition) {
            if (is_string($definition['id'] ?? null) && ! isset($byId[$definition['id']])) {
                $byId[$definition['id']] = $definition;
            }
        }

        $problems = [];
        foreach ($fields as $id => $value) {
            $definition = $byId[(string) $id] ?? null;

            if (($definition['type'] ?? null) === 'file') {
                $result = $this->fieldValue($value, (bool) ($definition['multiple'] ?? false), $resolve);
                foreach ($result['problems'] as $problem) {
                    $problems[] = ['field' => (string) $id, 'problem' => $problem];
                }
                $fields[$id] = $result['value'];

                continue;
            }

            $fields[$id] = $this->walk($value, $resolve, (string) $id, $problems);
        }

        return ['fields' => $fields, 'problems' => $problems];
    }

    /**
     * Rewrite the links in every string of a value, however deep it sits in lists.
     *
     * @param callable(string): ?array{id: int, name: string} $resolve
     * @param list<array{field: string, problem: string}>     $problems
     */
    private function walk(mixed $value, callable $resolve, string $field, array &$problems): mixed
    {
        if (is_string($value)) {
            $result = $this->rewriteText($value, $resolve);
            foreach ($result['missing'] as $path) {
                $problems[] = ['field' => $field, 'problem' => 'the link to ' . $path . ' points at a file that is not in the public folder, or could not be copied'];
            }

            return $result['text'];
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->walk($item, $resolve, $field, $problems);
            }
        }

        return $value;
    }

    /**
     * The new link of a file, with its name written so a space or a symbol cannot break it.
     *
     * @param array{id: int, name: string} $file
     */
    public static function link(array $file): string
    {
        return '/media/' . (int) $file['id'] . '/' . rawurlencode((string) $file['name']);
    }

    /**
     * The path inside a hexadecimal path of a link, or null when it is not hexadecimal.
     */
    private static function pathOf(string $hex): ?string
    {
        if ($hex === '' || strlen($hex) % 2 !== 0 || ! ctype_xdigit($hex)) {
            return null;
        }

        $path = urldecode((string) hex2bin($hex));

        return $path === '' || str_contains($path, "\0") ? null : $path;
    }

    /**
     * The path to look for from one stored item of a file field, or why there is none.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function pathOfItem(string $item): array
    {
        if (preg_match(self::LINK, $item, $m) === 1 && preg_match('~^(?:https?://[^/]+)?/?public/file-server/serve/[0-9A-Fa-f]+$~i', $item) === 1) {
            $path = self::pathOf($m[1]);

            return [$path, $path === null ? 'the link ' . $item . ' does not hold a path' : null];
        }
        if (preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*://~', $item) === 1) {
            return [null, 'the link ' . $item . ' points at another host or another place than the file server'];
        }

        return [ltrim(str_replace('\\', '/', $item), '/'), null];
    }
}
