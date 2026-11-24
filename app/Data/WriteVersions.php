<?php

namespace App\Data;

use CodeIgniter\Database\BaseConnection;

/**
 * Write versions, the numbers a saved list uses to tell that it is stale.
 *
 * Each model has its own in `hyper_models.write_version`. It rises by one whenever something a list of
 * that model shows changes: a create, an edit, a trash, a restore or a purge of one of its entries, and
 * every edit of the model itself. The global one in `hyper_counters` rises on every such change in any
 * model, and also when a user is renamed, because lists show usernames.
 *
 * A write raises them last, after StarDust has the change. Raised earlier, a list built between the
 * steps would read StarDust's old state and be cached under the new number.
 */
class WriteVersions
{
    private const GLOBAL = 'write_version';

    public function __construct(private ?BaseConnection $db = null)
    {
    }

    /**
     * Raise the version of each model by one, and the global version once.
     *
     * @param list<int|string> $modelIds
     */
    public function bump(array $modelIds): void
    {
        $modelIds = array_values(array_unique(array_map('intval', $modelIds)));
        if ($modelIds !== []) {
            $this->db()->query('UPDATE ' . $this->table('hyper_models') . ' SET write_version = write_version + 1 WHERE id IN (' . implode(',', $modelIds) . ')');
        }

        $this->bumpGlobal();
    }

    /**
     * Raise only the global version, for a change that belongs to no model, such as a renamed user.
     */
    public function bumpGlobal(): void
    {
        $this->db()->query('INSERT INTO ' . $this->table('hyper_counters') . ' (name, value) VALUES (?, 1) ON DUPLICATE KEY UPDATE value = value + 1', [self::GLOBAL]);
    }

    /**
     * The global version, which is 0 until the first change.
     */
    public function global(): int
    {
        $row = $this->db()->query('SELECT value FROM ' . $this->table('hyper_counters') . ' WHERE name = ?', [self::GLOBAL])->getRow();

        return (int) ($row->value ?? 0);
    }

    /**
     * The version of one model, or 0 when there is no such model.
     */
    public function forModel(int|string $modelId): int
    {
        $row = $this->db()->query('SELECT write_version FROM ' . $this->table('hyper_models') . ' WHERE id = ?', [(int) $modelId])->getRow();

        return (int) ($row->write_version ?? 0);
    }

    private function table(string $name): string
    {
        return $this->db()->prefixTable($name);
    }

    private function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }
}
