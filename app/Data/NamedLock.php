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
        $this->acquire($name);

        try {
            return $callback();
        } finally {
            $this->release($name);
        }
    }

    /**
     * Take the lock and keep it until `release()` or the end of the process. Use it when the id to lock is only
     * known partway through a write, such as after an insert that has not been committed yet.
     *
     * @throws RuntimeException When the lock is not free within the timeout.
     */
    public function acquire(string $name): void
    {
        $db      = $this->db ??= db_connect();
        $timeout = $this->timeoutSeconds > 0 ? $this->timeoutSeconds : (int) config('HyperEngine')->lockTimeoutSeconds;

        $got = $db->query('SELECT GET_LOCK(?, ?) AS got', [$this->scoped($name), $timeout])->getRow();
        if ((int) ($got->got ?? 0) !== 1) {
            throw new RuntimeException("Another save of this record is still running ($name). Try again in a moment.");
        }
    }

    public function release(string $name): void
    {
        try {
            ($this->db ??= db_connect())->query('SELECT RELEASE_LOCK(?)', [$this->scoped($name)]);
        } catch (Throwable) {
            // The lock is released with the connection anyway.
        }
    }

    /**
     * MySQL scopes a lock name to the whole server, so two sites on one server, or one site with two table prefixes, must not
     * share a name. The name carries a short code of the database and the table prefix.
     */
    private function scoped(string $name): string
    {
        $db = $this->db ??= db_connect();

        return 'hc.' . substr(md5($db->getDatabase() . '|' . $db->getPrefix()), 0, 10) . '.' . $name;
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
