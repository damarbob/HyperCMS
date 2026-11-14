<?php

namespace App\Data;

/**
 * Thrown by a store when a request names a record that cannot be read.
 *
 * The message is meant for the caller of the API, so a controller can return it as it is.
 */
class LookupException extends \RuntimeException
{
}
