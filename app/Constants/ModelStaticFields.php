<?php
namespace App\Constants;

/**
 * Class ModelStaticFields
 *
 * Groups static field names for models.
 */
class ModelStaticFields {
    public const EDITED_BY     = 'edited_by';
    public const DATE_MODIFIED = 'date_modified';
    public const FIELD_LIST = [self::EDITED_BY, self::DATE_MODIFIED];
}
