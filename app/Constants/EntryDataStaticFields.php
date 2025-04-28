<?php

namespace App\Constants;

/**
 * Class EntryDataStaticFields
 *
 * Groups static field names for entry data.
 */
class EntryDataStaticFields
{
    public const CREATED_BY = 'created_by';
    public const DATE_CREATED = 'date_created';
    public const DELETED_BY = 'deleted_by';
    public const DATE_DELETED = 'date_deleted';
    public const FIELD_LIST = [self::CREATED_BY, self::DATE_CREATED, self::DELETED_BY, self::DATE_DELETED];
}
