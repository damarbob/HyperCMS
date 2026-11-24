<?php

namespace App\Libraries\Upgrade;

use CodeIgniter\Database\BaseConnection;

/**
 * The schema version and the step cursors, kept as named rows in `hyper_counters`.
 */
final class CounterSchemaStore implements SchemaStore
{
    public const VERSION = 'schema_version';

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function version(): int
    {
        return $this->read(self::VERSION) ?? 0;
    }

    public function setVersion(int $version): void
    {
        $this->write(self::VERSION, $version);
    }

    public function cursor(int $step): ?int
    {
        return $this->read(self::cursorName($step));
    }

    public function setCursor(int $step, int $id): void
    {
        $this->write(self::cursorName($step), $id);
    }

    public function clearCursor(int $step): void
    {
        $this->db->table('hyper_counters')->where('name', self::cursorName($step))->delete();
    }

    private static function cursorName(int $step): string
    {
        return 'schema_step_' . $step . '_cursor';
    }

    private function read(string $name): ?int
    {
        $row = $this->db->table('hyper_counters')->select('value')->where('name', $name)->get()->getRowArray();

        return $row === null ? null : (int) $row['value'];
    }

    private function write(string $name, int $value): void
    {
        $this->db->query('INSERT INTO ' . $this->db->prefixTable('hyper_counters') . ' (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
    }
}
