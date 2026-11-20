<?php

namespace App\Data\Entries;

use LogicException;

/**
 * Every write to an entry. EntryStore hands create, update, trash, restore, emptyTrash and
 * clearHistory here, and each follows the order the plan sets out: HyperCMS rows first under a
 * named lock, then StarDust, then the write versions.
 *
 * This is a skeleton. Phase A5 fills it in and owns this file.
 */
class EntryWriter
{
    /**
     * Create an entry and return its public id.
     */
    public function create(array $data, int $userId): int
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Save a new version of an entry.
     */
    public function update(int $id, array $data, int $userId): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Move entries to the trash.
     */
    public function trash(array $ids, int $userId): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Bring entries back from the trash.
     */
    public function restore(array $ids): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Delete the trashed entries for good.
     */
    public function emptyTrash(): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Trash the live entries of the given models, as ModelStore does when it trashes a model.
     *
     * Each entry is trashed as `trash()` does, with its payload copied out of StarDust first.
     */
    public function trashForModels(array $modelIds, int $userId): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Bring back the entries of the given models, as ModelStore does when it restores a trashed model.
     *
     * The entries trashed before the model was, come back with it, as they did on StarDust 0.2.
     */
    public function restoreForModels(array $modelIds): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Delete every saved version of an entry except the current one.
     *
     * @return int|null The number of versions deleted, or null when the entry has no versions.
     */
    public function clearHistory(int|string $entryId): ?int
    {
        throw $this->notPorted(__FUNCTION__);
    }

    private function notPorted(string $method): LogicException
    {
        return new LogicException(static::class . '::' . $method . '() is not ported to StarDust 0.3 yet.');
    }
}
