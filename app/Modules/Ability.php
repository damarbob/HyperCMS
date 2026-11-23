<?php

namespace App\Modules;

/**
 * An ability a module adds. The name starts with the area of the module and has two or more lowercase words joined by
 * dots, such as `users.manage`. The label and the description are what the Roles screen shows.
 */
final class Ability
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $description = '',
    ) {
    }
}
