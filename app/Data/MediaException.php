<?php

namespace App\Data;

use RuntimeException;

/**
 * A media library action that cannot be done, with a message fit to show to the person who asked.
 */
final class MediaException extends RuntimeException
{
}
