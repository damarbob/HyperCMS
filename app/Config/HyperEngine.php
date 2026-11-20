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
     * The most entries a search or a sort on a model field may scan. 0 means no limit has been set yet.
     */
    public int $scanLimit = 0;

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
}
