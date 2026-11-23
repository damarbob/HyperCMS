<?php

namespace App\Modules;

/**
 * A screen a module adds to the settings. It appears in the settings menu, and the address is the one of a route the
 * module made with `ModuleRoutes::admin()`.
 */
final class SettingsScreen
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $url,
        public readonly ?string $ability = null,
    ) {
    }
}
