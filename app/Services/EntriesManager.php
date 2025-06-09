<?php

namespace App\Services;

use App\Models\EntriesModel;
use App\Models\EntryDataModel;

/**
 * Class EntriesManager
 *
 * Provides methods to manage entries and their associated data.
 * Supports operations such as creating, updating, deleting, purging,
 * and restoring entries and entry data.
 */
class EntriesManager
{
    /**
     * @var EntriesModel
     */
    protected EntriesModel $entriesModel;

    /**
     * @var EntryDataModel
     */
    protected EntryDataModel $entryDataModel;

    /**
     * @var self|null Cached singleton instance.
     */
    protected static ?self $instance = null;

    /**
     * Returns a shared instance of the EntriesManager.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (is_null(static::$instance)) {
            $entriesModel   = model('EntriesModel');
            $entryDataModel = model('EntryDataModel');
            static::$instance = new static($entriesModel, $entryDataModel);
        }
        return static::$instance;
    }

    /**
     * Constructor.
     *
     * @param EntriesModel   $entriesModel
     * @param EntryDataModel $entryDataModel
     */
    public function __construct(EntriesModel $entriesModel, EntryDataModel $entryDataModel)
    {
        $this->entriesModel   = $entriesModel;
        $this->entryDataModel = $entryDataModel;
    }

    /**
     * Retrieves all entries.
     *
     * @return array The entries as an array.
     */
    public function get(): array
    {
        return $this->entriesModel->getCustomBuilder()->get()->getResultArray();
    }

    /**
     * Retrieves all deleted entries.
     *
     * @return array The deleted entries as an array.
     */
    public function getDeleted(): array
    {
        return $this->entriesModel->getDeletedCustomBuilder()->get()->getResultArray();
    }

    /**
     * Counts the total number of entries.
     *
     * @return int|string The number of entries.
     */
    public function count(): int|string
    {
        return $this->entriesModel->getCustomBuilder()->countAllResults();
    }

    /**
     * Counts the entry data records for a specific entry.
     *
     * @param int $entryId
     *
     * @return int|string The count of entry data records.
     */
    public function countData(int $entryId): int|string
    {
        return $this->entryDataModel
            ->getCustomBuilder()
            ->where('entry_id', $entryId)
            ->countAllResults();
    }

    /**
     * Counts the total number of deleted entries.
     *
     * @return int|string The number of deleted entries.
     */
    public function countDeleted(): int|string
    {
        return $this->entriesModel->getDeletedCustomBuilder()->countAllResults();
    }

    /**
     * Finds a single entry by its ID.
     *
     * @param int $id
     *
     * @return array|false The entry data as an associative array, or false if not found.
     */
    public function find(int $id): array|false
    {
        $result = $this->entriesModel->getCustomBuilder()
            ->where('id', $id)
            ->get()
            ->getResultArray();
        return empty($result) ? false : $result[0];
    }

    /**
     * Finds multiple entries by their IDs.
     *
     * @param array $ids
     *
     * @return array|false The entries as an array, or false if none found.
     */
    public function findEntries(array $ids): array|false
    {
        $result = $this->entriesModel->getCustomBuilder()
            ->whereIn('id', $ids)
            ->get()
            ->getResultArray();
        return empty($result) ? false : $result;
    }

    /**
     * Finds a deleted entry by its ID.
     *
     * @param int $id
     *
     * @return array|false The deleted entry data, or false if not found.
     */
    public function findDeleted(int $id): array|false
    {
        $result = $this->entriesModel->getDeletedCustomBuilder()
            ->where('id', $id)
            ->get()
            ->getResultArray();
        return empty($result) ? false : $result[0];
    }

    /**
     * Finds multiple deleted entries by their IDs.
     *
     * @param array $ids
     *
     * @return array|false The deleted entries as an array, or false if none found.
     */
    public function findDeletedEntries(array $ids): array|false
    {
        $result = $this->entriesModel->getDeletedCustomBuilder()
            ->whereIn('id', $ids)
            ->get()
            ->getResultArray();
        return empty($result) ? false : $result;
    }

    /**
     * Creates a new entry and associated entry data.
     *
     * @param array $data   Entry data.
     * @param int   $userId The ID of the user creating the entry.
     *
     * @return int The newly created entry's ID.
     */
    public function create(array $data, int $userId): int
    {
        $this->entriesModel->save([
            'model_id'   => $data['model_id'],
            'creator_id' => $userId,
        ]);

        $id = $this->entriesModel->getInsertID();

        // Set the foreign key for entry data.
        $data['entry_id']   = $id;
        $data['creator_id'] = $userId;
        $this->entryDataModel->save($data);

        return $id;
    }

    /**
     * Updates entry data for a given entry.
     *
     * @param int   $entryId The entry ID.
     * @param array $data    The data to update.
     * @param int   $userId  The ID of the user performing the update.
     *
     * @return void
     */
    public function update(int $entryId, array $data, int $userId): void
    {
        $data['entry_id']   = $entryId;
        $data['creator_id'] = $userId;
        $this->entryDataModel->save($data);
    }

    /**
     * Updates multiple entries.
     *
     * @param array $entryIds The IDs of the entries to update.
     * @param array $data     The data to update.
     *
     * @return void
     */
    public function updateEntries(array $entryIds, array $data): void
    {
        $this->entriesModel->whereIn('id', $entryIds)
            ->set($data)
            ->update();
    }

    /**
     * Updates entry data for multiple entries.
     *
     * @param array $entryIds The IDs of the entries.
     * @param array $data     The data to update.
     *
     * @return void
     */
    public function updateData(array $entryIds, array $data): void
    {
        $this->entryDataModel->whereIn('entry_id', $entryIds)
            ->set($data)
            ->update();
    }

    /**
     * Soft deletes entries and their associated entry data.
     *
     * @param array $ids       The IDs of entries to delete.
     * @param int   $deleterId The ID of the user performing the deletion.
     *
     * @return void
     */
    public function deleteEntries(array $ids, int $deleterId): void
    {
        // Update deleter info.
        $this->updateEntries($ids, ['deleter_id' => $deleterId]);
        $this->updateData($ids, ['deleter_id' => $deleterId]);

        // Delete associated entry data.
        $this->entryDataModel->whereIn('entry_id', $ids)->delete();

        // Soft delete entries.
        $this->entriesModel->delete($ids);
    }

    /**
     * Permanently purges all soft-deleted entries and their related data.
     *
     * @return void
     */
    public function purgeDeleted(): void
    {
        // Purge entry data for deleted records.
        $this->entryDataModel->purgeDeleted();

        // Purge deleted entries.
        $this->entriesModel->purgeDeleted();
    }

    /**
     * Restores soft-deleted entries and their associated entry data.
     *
     * @param array $ids The entry IDs to restore.
     *
     * @return void
     */
    public function restore(array $ids): void
    {
        // Restore entry data by nullifying the deleted_at timestamps.
        $this->entryDataModel->withDeleted()
            ->whereIn('entry_id', $ids)
            ->set(['deleted_at' => null])
            ->update();

        // Restore the entries themselves.
        $this->entriesModel->withDeleted()
            ->whereIn('id', $ids)
            ->set(['deleted_at' => null])
            ->update();
    }
}
