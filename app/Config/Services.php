<?php

namespace Config;

use App\Data\Abilities;
use App\Data\Entries\StarDustEntries;
use App\Data\EntryLabels;
use App\Data\EntryStore;
use App\Data\Grants;
use App\Data\ModelAccess;
use App\Data\ModelStore;
use App\Data\Permissions;
use App\Services\FileServer;
use CodeIgniter\Config\BaseService;
use PDO;
use StarDust\Config\Config as StarDustConfig;
use StarDust\StarDust;

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
        return new EntryStore();
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
        return new ModelStore();
    }

    /**
     * ModelAccess service.
     *
     * @param bool $getShared Whether to return a shared instance.
     * @return ModelAccess
     */
    public static function modelAccess(bool $getShared = true): ModelAccess
    {
        if ($getShared) {
            return static::getSharedInstance('modelAccess');
        }
        return new ModelAccess(static::modelStore(), service('settings'));
    }

    /**
     * The names of entries, which lists and pickers show instead of the entry number.
     *
     * @param bool $getShared Whether to return a shared instance.
     * @return EntryLabels
     */
    public static function entryLabels(bool $getShared = true): EntryLabels
    {
        if ($getShared) {
            return static::getSharedInstance('entryLabels');
        }
        return new EntryLabels(db_connect(), new StarDustEntries(static::stardust(), (int) config('HyperEngine')->tenantId));
    }

    /**
     * Permissions service, which answers what a user may do in the admin.
     *
     * @param bool $getShared Whether to return a shared instance.
     * @return Permissions
     */
    public static function permissions(bool $getShared = true): Permissions
    {
        if ($getShared) {
            return static::getSharedInstance('permissions');
        }
        return new Permissions(static::grants());
    }

    /**
     * The abilities of the admin: those of core, those modules register, and the ones an admin made.
     *
     * @param bool $getShared Whether to return a shared instance.
     * @return Abilities
     */
    public static function abilities(bool $getShared = true): Abilities
    {
        if ($getShared) {
            return static::getSharedInstance('abilities');
        }
        return new Abilities(db_connect());
    }

    /**
     * What each role may do, read from and written to the database.
     *
     * @param bool $getShared Whether to return a shared instance.
     * @return Grants
     */
    public static function grants(bool $getShared = true): Grants
    {
        if ($getShared) {
            return static::getSharedInstance('grants');
        }
        return new Grants(db_connect(), static::abilities());
    }

    /**
     * The StarDust 0.3 engine, built the first time something asks for it, so a request that never
     * touches entries opens no second database connection.
     *
     * StarDust needs PDO and CodeIgniter's MySQLi driver cannot share a transaction with it, so this
     * connection is its own. The logger is CodeIgniter's, because StarDust's default one writes to
     * standard output and in a web request that text would land inside the HTTP response.
     *
     * @param bool $getShared Whether to return a shared instance.
     * @return StarDust
     */
    public static function stardust(bool $getShared = true): StarDust
    {
        if ($getShared) {
            return static::getSharedInstance('stardust');
        }

        /** @var HyperEngine $engine */
        $engine = config('HyperEngine');
        $dirs   = [
            'pid'       => $engine->pidFileDir !== '' ? $engine->pidFileDir : WRITEPATH . 'stardust' . DIRECTORY_SEPARATOR . 'pid',
            'artifacts' => $engine->artifactDir !== '' ? $engine->artifactDir : WRITEPATH . 'stardust' . DIRECTORY_SEPARATOR . 'artifacts',
        ];
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        return new StarDust(new StarDustConfig(
            pdo: static::stardustConnection(),
            logger: static::logger(),
            artifactDir: $dirs['artifacts'],
            pidFileDir: $dirs['pid'],
            tickBudgetSeconds: $engine->tickBudgetSeconds,
            tablePrefix: $engine->tablePrefix,
        ));
    }

    /**
     * A PDO connection to the same database as CodeIgniter's default group, with errors thrown as
     * exceptions and native prepared statements.
     */
    protected static function stardustConnection(): PDO
    {
        $db  = config('Database')->{config('Database')->defaultGroup};
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['hostname'],
            (int) ($db['port'] ?? 3306),
            $db['database'],
        );

        $options = [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // CodeIgniter's `encrypt` is false, true, or an array of ssl_* keys. A bare true asks for
        // a TLS connection without checking the server certificate, as the MySQLi driver does.
        $encrypt = $db['encrypt'] ?? false;
        if ($encrypt) {
            $keys = [
                'ssl_key'    => PDO::MYSQL_ATTR_SSL_KEY,
                'ssl_cert'   => PDO::MYSQL_ATTR_SSL_CERT,
                'ssl_ca'     => PDO::MYSQL_ATTR_SSL_CA,
                'ssl_capath' => PDO::MYSQL_ATTR_SSL_CAPATH,
                'ssl_cipher' => PDO::MYSQL_ATTR_SSL_CIPHER,
            ];
            $given = is_array($encrypt) ? $encrypt : [];
            foreach ($keys as $key => $attribute) {
                if (! empty($given[$key])) {
                    $options[$attribute] = $given[$key];
                }
            }
            if (count(array_intersect_key($options, array_flip($keys))) === 0) {
                $options[PDO::MYSQL_ATTR_SSL_CIPHER] = 'DEFAULT';
            }
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool) ($given['ssl_verify'] ?? false);
        }

        return new PDO($dsn, (string) $db['username'], (string) $db['password'], $options);
    }
}
