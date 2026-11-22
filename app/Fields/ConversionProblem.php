<?php

namespace App\Fields;

use RuntimeException;

/**
 * A field or a value of the old format that cannot be converted. The message is for the upgrade report.
 */
final class ConversionProblem extends RuntimeException
{
}
