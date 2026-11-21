<?php

namespace App\Data;

use RuntimeException;
use Throwable;

/**
 * Runs HyperCMS rows in one transaction that is rolled back when anything fails.
 *
 * With `DBDebug` on, CodeIgniter throws from inside a query and `transComplete()` never runs, so a bare
 * `transStart()` and `transComplete()` pair would leave the transaction open. The class that uses this trait
 * has a `db()` method that returns the connection.
 */
trait Transacts
{
    /**
     * Run the callback in a transaction and return what it returns.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws RuntimeException When a query inside it failed without throwing.
     */
    protected function transact(callable $callback): mixed
    {
        $db = $this->db();
        $db->transBegin();

        try {
            $result = $callback();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        if (! $db->transStatus()) {
            $db->transRollback();

            throw new RuntimeException('Could not save the changes: ' . ($db->error()['message'] ?? 'unknown error'));
        }
        $db->transCommit();

        return $result;
    }
}
