<?php

namespace App\Services;

use App\Models\EntriesModel;
use App\Models\EntryDataModel;

class EntriesManager
{
    protected EntriesModel $entriesModel;
    protected EntryDataModel $entryDataModel;
    protected static $instance;

    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            $entriesModel = model('entriesModel');
            $entryDataModel = model('entryDataModel');
            static::$instance = new static(
                $entriesModel,
                $entryDataModel
            );
        }
        return static::$instance;
    }

    public function __construct(
        EntriesModel $entriesModel,
        EntryDataModel $entryDataModel
    ) {
        $this->entriesModel = $entriesModel;
        $this->entryDataModel = $entryDataModel;
    }

    public function get(): array
    {
        return $this->entriesModel->getCustomBuilder()->get()->getResultArray();
    }


    public function getDeleted(): array
    {
        return $this->entriesModel->getDeletedCustomBuilder()->get()->getResultArray();
    }

    public function count(): int | string
    {
        return $this->entriesModel->getCustomBuilder()->countAllResults();
    }

    public function countDeleted(): int | string
    {
        return $this->entriesModel->getDeletedCustomBuilder()->countAllResults();
    }

    public function find(int $id): array | false
    {
        $result = $this->entriesModel->getCustomBuilder()->where('id', $id)->get()->getResultArray();

        if (empty($result)) {
            return false;
        }

        return $result[0];
    }

    public function findEntries(array $ids): array | false
    {
        $result = $this->entriesModel->getCustomBuilder()->whereIn('id', $ids)->get()->getResultArray();

        if (empty($result)) {
            return false;
        }

        return $result;
    }

    public function findDeleted(int $id): array | false
    {
        $result = $this->entriesModel->getDeletedCustomBuilder()->where('id', $id)->get()->getResultArray();

        if (empty($result)) {
            return false;
        }

        return $result[0];
    }

    public function findDeletedEntries(array $ids): array | false
    {
        $result = $this->entriesModel->getDeletedCustomBuilder()->whereIn('id', $ids)->get()->getResultArray();

        if (empty($result)) {
            return false;
        }

        return $result;
    }

    public function create(array $data, int $userId): int
    {
        $this->entriesModel->save([
            'model_id' => $data['model_id'],
            'creator_id' => $userId,
        ]);
        $id = $this->entriesModel->getInsertID();

        $data['entry_id'] = $id;
        $data['creator_id'] = $userId;
        $this->entryDataModel->save($data);

        return $id;
    }

    public function update(int $entryId, array $data, int $userId): void
    {
        $data['entry_id'] = $entryId;
        $data['creator_id'] = $userId;
        $this->entryDataModel->save($data);
    }

    public function updateEntries(array $entryIds, array $data): void
    {
        $this->entriesModel
            ->whereIn('id', $entryIds)
            ->set($data)
            ->update();
    }

    public function updateData(array $entryIds, array $data): void
    {
        $this->entryDataModel
            ->whereIn('entry_id', $entryIds)
            ->set($data)
            ->update();
    }

    public function deleteEntries(array $ids, int $deleterId): void
    {
        // Update entries and data
        $this->updateEntries($ids, ['deleter_id' => $deleterId]);
        $this->updateData($ids, ['deleter_id' => $deleterId]);

        // Delete all related "entry_data" records using whereIn for bulk deletion.
        $this->entryDataModel->whereIn('entry_id', $ids)->delete();

        // Bulk delete entries.
        $this->entriesModel->delete($ids);
    }

    public function purgeDeleted(): void
    {
        // Delete all related "entry_data" records using whereIn for bulk deletion.
        $this->entryDataModel->purgeDeleted();

        // Bulk delete entries.
        $this->entriesModel->purgeDeleted();
    }

    public function restore(array $ids): void
    {
        // For soft deletes, "restoring" means updating the deleted_at column to NULL.
        // Restore associated entry_data records.
        $this->entryDataModel
            ->withDeleted()
            ->whereIn('entry_id', $ids)
            ->set(['deleted_at' => null])
            ->update();

        // Restore the entries themselves.
        $this->entriesModel
            ->withDeleted()
            ->whereIn('id', $ids)
            ->set(['deleted_at' => null])
            ->update();
    }
}
