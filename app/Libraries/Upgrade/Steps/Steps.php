<?php

namespace App\Libraries\Upgrade\Steps;

use App\Data\Entries\StarDustEntries;
use App\Data\WriteVersions;
use App\Fields\FieldTypes;
use App\Libraries\Upgrade\Convert\EntryValues;
use App\Libraries\Upgrade\Convert\ModelFields;
use App\Libraries\Upgrade\ConversionStep;
use CodeIgniter\Database\BaseConnection;
use Config\HyperEngine;

/**
 * The conversion steps of the upgrade, in order. The schema version of a database that has had all of them is `LATEST`,
 * which is also the version a fresh install starts at.
 */
final class Steps
{
    /**
     * The number of the last step. A new step raises it, and a test checks it against the list.
     */
    public const LATEST = 4;

    /**
     * @return list<ConversionStep>
     */
    public static function make(BaseConnection $db): array
    {
        $types  = FieldTypes::core();
        $engine = new StarDustEntries(service('stardust'), (int) config(HyperEngine::class)->tenantId);

        return [
            new ConvertModelFields($db, new ModelFields($types)),
            new PickLabelFields($db),
            new ConvertEntryValues($db, new EntryValues($types), $engine, new WriteVersions($db)),
            new ConvertPermissions($db),
        ];
    }
}
