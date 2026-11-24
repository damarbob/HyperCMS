<?php

namespace App\Libraries\Upgrade;

use CodeIgniter\Database\BaseConnection;
use JsonException;
use Throwable;

/**
 * Knows whether this site's data is behind the code, and keeps what the upgrade page needs between its requests.
 *
 * A site is behind when it still holds the old layout and its upgrade never finished, or when its schema version is lower than
 * the last conversion step. Asking the database on every request would cost a query each time, so a marker file records the
 * version the site was last found to be at. While the marker matches the code, nothing else is read.
 *
 * The upgrade page uses a key file in the same way as the install page. Whoever runs the upgrade must be able to read a file in
 * the writable folder, so a visitor cannot start it.
 */
final class UpgradeState
{
    public const MARKER   = '.hyper_schema_ok';
    public const KEY      = 'upgrade.key';
    public const PROGRESS = 'upgrade.json';

    public const FINISHED = 'upgrade_finished';

    private readonly string $folder;

    public function __construct(string $folder)
    {
        $this->folder = rtrim($folder, '/\\') . DIRECTORY_SEPARATOR;
    }

    public static function current(): self
    {
        return new self(WRITEPATH);
    }

    /**
     * The rule. The old tables with no finished mark mean an upgrade that never ran or never ended. A schema version below the
     * latest step means conversions are waiting. Without the counters table there is no version to read.
     */
    public static function behind(bool $oldLayout, bool $finishedMark, ?int $version, int $latest): bool
    {
        if ($oldLayout && ! $finishedMark) {
            return true;
        }

        return $version !== null && $version < $latest;
    }

    /**
     * Whether the marker says this site was last found to be at the latest version.
     */
    public function known(int $latest): bool
    {
        $path = $this->folder . self::MARKER;

        return is_file($path) && trim((string) @file_get_contents($path)) === (string) $latest;
    }

    public function remember(int $latest): void
    {
        @file_put_contents($this->folder . self::MARKER, $latest . "\n", LOCK_EX);
    }

    /**
     * Whether the data is behind the code. Null when the database could not tell, so a site whose database is down is not sent
     * to an upgrade page.
     */
    public function needed(BaseConnection $db, int $latest): ?bool
    {
        if ($this->known($latest)) {
            return false;
        }

        try {
            $oldLayout = $db->getPrefix() === '';
            foreach (Source::TABLES as $table) {
                $oldLayout = $oldLayout && $db->tableExists($table);
            }

            $counters = $db->tableExists('hyper_counters');
            $finished = $counters && $db->table('hyper_counters')->where('name', self::FINISHED)->countAllResults() > 0;
            $version  = $counters ? (new CounterSchemaStore($db))->version() : null;

            $behind = self::behind($oldLayout, $finished, $version, $latest);
        } catch (Throwable) {
            return null;
        }

        if (! $behind) {
            $this->remember($latest);
        }

        return $behind;
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
     * @return array<string, mixed>|null
     */
    public function progress(): ?array
    {
        $path = $this->folder . self::PROGRESS;
        if (! is_file($path)) {
            return null;
        }

        try {
            $progress = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($progress) ? $progress : null;
    }

    /**
     * @param array<string, mixed> $progress
     */
    public function saveProgress(array $progress): void
    {
        $this->writePrivate($this->folder . self::PROGRESS, json_encode($progress, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /**
     * Remove the key and the progress when the upgrade has ended for good.
     */
    public function finish(int $latest): void
    {
        $this->remember($latest);
        @unlink($this->folder . self::KEY);
        @unlink($this->folder . self::PROGRESS);
    }

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
}
