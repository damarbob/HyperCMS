<?php

namespace Config;

use App\Data\EntryStore;
use App\Data\ModelStore;
use App\Services\FileServer;
use CodeIgniter\Config\BaseService;
use StarDust\Models\EntriesModel;
use StarDust\Models\EntryDataModel;
use StarDust\Models\ModelDataModel;
use StarDust\Models\ModelsModel;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    /**
     * FileServer service.
     *
     * @param bool $getShared Whether to return a shared instance.
     * @return FileServer
     */
    public static function fileServer(bool $getShared = true): FileServer
    {
        if ($getShared) {
            return static::getSharedInstance('fileServer');
        }
        return new FileServer();
    }

    /**
     * EntryStore service.
     *
     * @param bool $getShared Whether to return a shared instance.
     * @return EntryStore
     */
    public static function entryStore(bool $getShared = true): EntryStore
    {
        if ($getShared) {
            return static::getSharedInstance('entryStore');
        }
        return new EntryStore(model(EntriesModel::class), model(EntryDataModel::class), static::modelStore(), service('entriesManager'));
    }

    /**
     * ModelStore service.
     *
     * @param bool $getShared Whether to return a shared instance.
     * @return ModelStore
     */
    public static function modelStore(bool $getShared = true): ModelStore
    {
        if ($getShared) {
            return static::getSharedInstance('modelStore');
        }
        return new ModelStore(model(ModelsModel::class), model(ModelDataModel::class), service('modelsManager'));
    }
}
