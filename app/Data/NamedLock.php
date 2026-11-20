<?php

namespace App\Data;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * A MySQL named lock, so that one process at a time changes a given entry or model.
 *
 * A write runs in three steps, HyperCMS rows then StarDust then HyperCMS rows again, and CodeIgniter and
 * StarDust cannot share a transaction. Without the lock, two admins saving the same entry at once could
 * interleave so that StarDust ends with one save and the history with the other. The lock belongs to the
 * database connection, so it is released when the process ends, and `GET_LOCK` waits for it.
 */
class NamedLock
{
    public function __construct(private ?BaseConnection $db = null, private int $timeoutSeconds = 0)
    {
    }

    /**
     * Run the callback while holding the lock, and release it afterwards.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws RuntimeException When the lock is not free within the timeout.
     */
    public function run(string $name, callable $callback): mixed
    {
        $db      = $this->db ??= db_connect();
        $timeout = $this->timeoutSeconds > 0 ? $this->timeoutSeconds : (int) config('HyperEngine')->lockTimeoutSeconds;

        $got = $db->query('SELECT GET_LOCK(?, ?) AS got', [$name, $timeout])->getRow();
        if ((int) ($got->got ?? 0) !== 1) {
            throw new RuntimeException("Another save of this record is still running ($name). Try again in a moment.");
        }

        try {
            return $callback();
        } finally {
            try {
                $db->query('SELECT RELEASE_LOCK(?)', [$name]);
            } catch (Throwable) {
                // The lock is released with the connection anyway.
            }
        }
    }

    public static function forEntry(int|string $id): string
    {
        return 'hyper_entry_' . (int) $id;
    }

    public static function forModel(int|string $id): string
    {
        return 'hyper_model_' . (int) $id;
    }
}
