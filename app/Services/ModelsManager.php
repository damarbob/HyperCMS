<?php

namespace App\Services;

use App\Models\EntriesModel;
use App\Models\EntryDataModel;
use App\Models\ModelsModel;
use App\Models\ModelDataModel;

class ModelsManager
{
    protected ModelsModel $modelsModel;
    protected ModelDataModel $modelDataModel;
    protected EntriesModel $entriesModel;
    protected EntryDataModel $entryDataModel;
    protected static $instance;

    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            $modelsModel = model('modelsModel');
            $modelDataModel = model('modelDataModel');
            $entriesModel = model('entriesModel');
            $entryDataModel = model('entryDataModel');
            static::$instance = new static(
                $modelsModel,
                $modelDataModel,
                $entriesModel,
                $entryDataModel
            );
        }
        return static::$instance;
    }

    public function __construct(
        ModelsModel $modelsModel,
        ModelDataModel $modelDataModel,
        EntriesModel $entriesModel,
        EntryDataModel $entryDataModel
    ) {
        $this->modelsModel = $modelsModel;
        $this->modelDataModel = $modelDataModel;
        $this->entriesModel = $entriesModel;
        $this->entryDataModel = $entryDataModel;
    }

    public function get(): array
    {
        return $this->modelsModel->getCustomBuilder()->get()->getResultArray();
    }


    public function getDeleted(): array
    {
        return $this->modelsModel->getDeletedCustomBuilder()->get()->getResultArray();
    }

    public function count(): int | string
    {
        return $this->modelsModel->getCustomBuilder()->countAllResults();
    }

    public function countDeleted(): int | string
    {
        return $this->modelsModel->getDeletedCustomBuilder()->countAllResults();
    }

    public function find(int $id): array | false
    {
        $modelResult = $this->modelsModel->getCustomBuilder()->where('id', $id)->get()->getResultArray();

        if (empty($modelResult)) {
            return false;
        }

        return $modelResult[0];
    }

    public function findModels(array $ids): array | false
    {
        $modelResult = $this->modelsModel->getCustomBuilder()->whereIn('id', $ids)->get()->getResultArray();

        if (empty($modelResult)) {
            return false;
        }

        return $modelResult;
    }

    public function findDeleted(int $id): array | false
    {
        $modelResult = $this->modelsModel->getDeletedCustomBuilder()->where('id', $id)->get()->getResultArray();

        if (empty($modelResult)) {
            return false;
        }

        return $modelResult[0];
    }

    public function findDeletedModels(array $ids): array | false
    {
        $modelResult = $this->modelsModel->getDeletedCustomBuilder()->whereIn('id', $ids)->get()->getResultArray();

        if (empty($modelResult)) {
            return false;
        }

        return $modelResult;
    }

    public function create(array $data, int $userId): int
    {
        $this->modelsModel->save(['creator_id' => $userId]);
        $modelId = $this->modelsModel->getInsertID();

        $data['model_id'] = $modelId;
        $data['creator_id'] = $userId;
        $this->modelDataModel->save($data);

        return $modelId;
    }

    public function update(int $modelId, array $data, int $userId): void
    {
        $data['model_id'] = $modelId;
        $data['creator_id'] = $userId;
        $this->modelDataModel->save($data);
    }

    public function updateModels(array $ids, array $data): void
    {
        $this->modelsModel
            ->whereIn('id', $ids)
            ->set($data)
            ->update();
    }

    public function updateData(array $modelIds, array $data): void
    {
        $this->modelDataModel
            ->whereIn('model_id', $modelIds)
            ->set($data)
            ->update();
    }

    public function deleteModels(array $ids, int $deleterId): void
    {
        // Update models and data
        $this->updateModels($ids, ['deleter_id' => $deleterId]);
        $this->updateData($ids, ['deleter_id' => $deleterId]);

        $this->modelDataModel->whereIn('model_id', $ids)->delete();

        $entryIds = array_column(
            $this->entriesModel->select('id')->whereIn('model_id', $ids)->findAll(),
            'id'
        );

        if (!empty($entryIds)) {
            $this->entryDataModel->whereIn('entry_id', $entryIds)
                ->set(['deleter_id' => $deleterId])->update();
            $this->entryDataModel->whereIn('entry_id', $entryIds)->delete();

            $this->entriesModel->whereIn('model_id', $ids)
                ->set(['deleter_id' => $deleterId])->update();
            $this->entriesModel->whereIn('model_id', $ids)->delete();
        }

        $this->modelsModel->delete($ids);
    }

    public function purgeDeleted(): void
    {
        $modelIds = array_column(
            $this->modelsModel->select('id')->onlyDeleted()->findAll(),
            'id'
        );

        if (empty($modelIds)) return;

        $entryIds = array_column(
            $this->entriesModel->select('id')->withDeleted()->whereIn('model_id', $modelIds)->findAll(),
            'id'
        );

        $this->modelDataModel->whereIn('model_id', $modelIds)->delete(purge: true);

        if (!empty($entryIds)) {
            $this->entryDataModel->whereIn('entry_id', $entryIds)->delete(purge: true);
        }

        $this->modelsModel->purgeDeleted();
        $this->entriesModel->purgeDeleted();
    }

    public function restore(array $ids): void
    {
        $this->modelDataModel->withDeleted()
            ->whereIn('model_id', $ids)->set(['deleted_at' => null])->update();
        $this->modelsModel->withDeleted()
            ->whereIn('id', $ids)->set(['deleted_at' => null])->update();

        $entryIds = array_column(
            $this->entriesModel->withDeleted()->whereIn('model_id', $ids)->findAll(),
            'id'
        );

        if (!empty($entryIds)) {
            $this->entryDataModel->withDeleted()
                ->whereIn('entry_id', $entryIds)->set(['deleted_at' => null])->update();
            $this->entriesModel->withDeleted()
                ->whereIn('id', $entryIds)->set(['deleted_at' => null])->update();
        }
    }
}
