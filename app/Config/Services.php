<?php

namespace Config;

use App\Services\EntriesManager;
use App\Services\FileServer;
use App\Services\HyperHooks;
use App\Services\ModelsManager;
use CodeIgniter\Config\BaseService;

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
     * Returns an instance of the HyperHooks class.
     */
    public static function hooks(bool $getShared = true): HyperHooks
    {
        if ($getShared) {
            return static::getSharedInstance('hooks');
        }
        return HyperHooks::getInstance();
    }

    /**
     * Returns an instance of the ModelsManager class.
     */
    public static function modelsManager(bool $getShared = true): ModelsManager
    {
        if ($getShared) {
            return static::getSharedInstance('modelsManager');
        }
        return ModelsManager::getInstance();
    }

    /**
     * Returns an instance of the EntriesManager class.
     */
    public static function entriesManager(bool $getShared = true): EntriesManager
    {
        if ($getShared) {
            return static::getSharedInstance('entriesManager');
        }
        return EntriesManager::getInstance();
    }

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
}
