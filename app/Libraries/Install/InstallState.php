<?php

namespace App\Libraries\Install;

use JsonException;

/**
 * Decides whether the install page may be used, and keeps what it needs between its requests.
 *
 * The page is open only while the site is not installed and either nothing is configured yet or an install is under way.
 * "Under way" means the install key file exists. That file is made when the page is first opened, and its content has to be
 * typed into the form, so only someone who can read the site's files can finish an install. When the install is done the
 * marker file is written and the key and the saved answers are deleted. After that the page does not exist, even if the
 * database is down, because the marker is on disk.
 */
final class InstallState
{
    public const MARKER = '.hyper_setup_done';
    public const KEY    = 'install.key';
    public const ANSWER = 'install.json';

    private readonly string $folder;

    public function __construct(string $folder, private readonly bool $databaseConfigured)
    {
        $this->folder = rtrim($folder, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * The state of this site, read from the writable folder and the database settings in use.
     */
    public static function current(): self
    {
        $config = config('Database');
        $group  = $config->{$config->defaultGroup} ?? [];

        return new self(WRITEPATH, trim((string) ($group['database'] ?? '')) !== '');
    }

    public function installed(): bool
    {
        return is_file($this->folder . self::MARKER);
    }

    public function open(): bool
    {
        if ($this->installed()) {
            return false;
        }

        return is_file($this->folder . self::KEY) || ! $this->databaseConfigured;
    }

    /**
     * The key to type into the form. It is made the first time it is asked for.
     */
    public function key(): string
    {
        $path = $this->folder . self::KEY;

        if (! is_file($path)) {
            $this->writePrivate($path, bin2hex(random_bytes(16)) . "\n");
        }

        return trim((string) file_get_contents($path));
    }

    public function keyMatches(?string $given): bool
    {
        $given = trim((string) $given);

        return $given !== '' && is_file($this->folder . self::KEY) && hash_equals($this->key(), $given);
    }

    public function keyPath(): string
    {
        return $this->folder . self::KEY;
    }

    /**
     * @param array<string, mixed> $answers
     */
    public function saveAnswers(array $answers): void
    {
        $this->writePrivate($this->folder . self::ANSWER, json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Writes a file that only the owner may read, with that permission from the first byte and not after the write.
     */
    private function writePrivate(string $path, string $content): void
    {
        $previous = umask(0o077);

        try {
            file_put_contents($path, $content, LOCK_EX);
            @chmod($path, 0o600);
        } finally {
            umask($previous);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function answers(): ?array
    {
        $path = $this->folder . self::ANSWER;
        if (! is_file($path)) {
            return null;
        }

        try {
            $answers = json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($answers) ? $answers : null;
    }

    /**
     * Lock the page for good and remove what only the install needed.
     */
    public function finish(): void
    {
        file_put_contents($this->folder . self::MARKER, date('c') . "\n");
        @unlink($this->folder . self::KEY);
        @unlink($this->folder . self::ANSWER);
    }
}
