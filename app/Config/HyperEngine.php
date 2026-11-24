<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Settings for the StarDust 0.3 engine that keeps the current fields of every entry.
 * Any value can be overridden in .env, for example `hyperEngine.tablePrefix = 'sd_'`.
 */
class HyperEngine extends BaseConfig
{
    /**
     * Prefix of StarDust's own tables, so they sit in HyperCMS's database as sd_entry_data,
     * sd_stardust_models and so on. It is separate from CodeIgniter's DBPrefix.
     */
    public string $tablePrefix = 'sd_';

    /**
     * The one StarDust tenant every HyperCMS entry belongs to.
     */
    public int $tenantId = 1;

    /**
     * Where StarDust keeps the pid files of its background runs. Empty means writable/stardust/pid.
     */
    public string $pidFileDir = '';

    /**
     * Where StarDust writes export and bulk import artifacts. Empty means writable/stardust/artifacts.
     */
    public string $artifactDir = '';

    /**
     * Seconds a saved list of entry ids stays in the cache before it is built again.
     */
    public int $savedListExpiry = 600;

    /**
     * The most entries a search or a sort on a model field may scan. 0 means no limit. The figure comes from the
     * A8 measurements, where memory grew about 1 MB per 1,000 entries and reached 128 MB near 70,000.
     */
    public int $scanLimit = 20000;

    /**
     * The most bytes of stored fields a search or a sort on a model field may read in one request. 0 means no
     * limit. A search costs about 33 ms per MB, so 600 MB is about 20 seconds, under PHP's 30 second web limit.
     */
    public int $scanBytes = 629_145_600;

    /**
     * The most entries the token API counts for the total of a list filtered on an indexed field. StarDust has no
     * count, so a count reads the ids of the matches a thousand at a time, and a larger match set has no total.
     */
    public int $filterCountLimit = 10000;

    /**
     * The most live entries a model may hold for flagging a field filterable to clean their values at once, inside
     * the request that saves the model. A larger model is left to `php spark hyper:normalize`, which has no time limit.
     */
    public int $normaliseLimit = 2000;

    /**
     * The most bytes of stored fields one getMany() call may be asked for, because bodies of 1 to 3 MB
     * exist and PHP in Docker stops at 128 MB.
     */
    public int $chunkBytes = 8_388_608;

    /**
     * The most entries one getMany() call may be asked for.
     */
    public int $chunkEntries = 1000;

    /**
     * Seconds one `php spark hyper:tick` run may spend when no --budget is given.
     */
    public int $tickBudgetSeconds = 50;

    /**
    /**
     * The secret that lets a scheduled URL fetch run the background work at `hyper/tick`, for a host with no cron line.
     * At least 32 characters. Empty means the address does not exist. Set it in .env as `hyperEngine.tickSecret`, and
     * never run the cron line as well.
     */
    public string $tickSecret = '';

     * Seconds a save waits for another save of the same entry or model before it gives up.
     */
    public int $lockTimeoutSeconds = 10;

    /**
     * The table that holds the people who create, edit and delete models and entries, the column
     * that is its primary key, and the column that lists show as their name.
     */
    public string $usersTable          = 'users';
    public string $usersIdColumn       = 'id';
    public string $usersUsernameColumn = 'username';
}
