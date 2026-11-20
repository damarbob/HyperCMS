<?php

namespace App\Data\Entries;

use LogicException;

/**
 * Single and module reads of entries: one entry, several entries for modules, the trash, counts
 * and the history of one entry. A row is a hyper_entries row plus its fields from StarDust,
 * assembled by EntryRows.
 *
 * This is a skeleton. Phase A6 fills it in and owns this file.
 */
class EntryReader
{
    /**
     * Read one live entry as a row with StarDust's own column names, or false when there is none.
     */
    public function findArray(int $id): array|false
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read one live entry with the legacy column names, or null when there is none.
     */
    public function findAliased(int|string $id): ?array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read one entry by its id, or null when there is none.
     */
    public function findRow($id): ?array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read the id of the model an entry belongs to, or null when the entry has no saved version.
     */
    public function modelIdOf(int|string $entryId): ?int
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read every live entry as rows with StarDust's own column names.
     */
    public function allArray(): array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read the live entries of the given models, with the legacy column names.
     */
    public function ofModels(array $modelIds): array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read the live entries of the model with this name, with the legacy column names.
     */
    public function named(string $modelName): array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read live entries newest first, with the legacy column names.
     *
     * @param array $filters Optional keys `id`, `ids`, `model_id` and `hidden_model_ids`. An empty filter is skipped.
     */
    public function recent(array $filters = []): array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read the newest live entry of a model that has no field values, or null when there is none.
     */
    public function blankFor(int|string $modelId): ?array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read the trashed entries among the given ids, or false when there are none.
     */
    public function trashed(array $ids): array|false
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Count the entries in the trash.
     */
    public function countTrashed(): int|string
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Count the live entries of one model.
     */
    public function countForModel(int|string $modelId): int
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read the saved versions of one entry as rows.
     */
    public function versionsOf(int|string $entryId): array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Compile the SQL that reads every live entry as a flat table, without running it.
     */
    public function compiledSelect(): string
    {
        throw $this->notPorted(__FUNCTION__);
    }

    private function notPorted(string $method): LogicException
    {
        return new LogicException(static::class . '::' . $method . '() is not ported to StarDust 0.3 yet.');
    }
}
