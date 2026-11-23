<?php

namespace App\Modules;

use App\Data\Abilities;
use App\Fields\FieldType;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Settings\Settings;
use InvalidArgumentException;
use Throwable;

/**
 * The modules that are loaded, and what they offer to core.
 *
 * A module is loaded when it is on the list of active modules and has a `Module` class in its own namespace. A module
 * that was written before the contract has no such class and keeps working through its `init.php` until it is moved.
 */
final class Modules
{
    /**
     * @var array<string, Module>
     */
    private array $modules = [];

    /**
     * Why a module was not loaded, by module name.
     *
     * @var array<string, string>
     */
    private array $skipped = [];

    private bool $booted = false;

    public function __construct(private readonly Abilities $abilities, private readonly Slots $slots, private readonly Settings $settings, private readonly string $coreVersion)
    {
    }

    /**
     * Load the modules of the list that follow the contract. Safe to call twice.
     *
     * @param list<string> $names
     */
    public function boot(array $names): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        foreach ($names as $name) {
            $class = $name . '\\Module';
            if (! class_exists($class)) {
                continue;
            }
            if (! is_subclass_of($class, Module::class)) {
                $this->skipped[$name] = "$class does not extend " . Module::class . '.';

                continue;
            }

            try {
                $this->register(new $class());
            } catch (Throwable $e) {
                $this->skipped[$name] = $e->getMessage();
                log_message('error', 'Module {name} was not loaded. {message}', ['name' => $name, 'message' => $e->getMessage()]);
            }
        }
    }

    /**
     * Load one module: check the core version it asks for, and take its abilities and slots.
     */
    public function register(Module $module): void
    {
        if (! self::satisfies($this->coreVersion, $module->requires())) {
            $this->skipped[$module->name()] = 'It needs core ' . $module->requires() . ' and this is ' . $this->coreVersion . '.';

            return;
        }

        foreach ($module->abilities() as $ability) {
            if (preg_match(Abilities::NAME, $ability->name) !== 1 || in_array($ability->name, Abilities::CORE, true) || Abilities::isModelAbility($ability->name)) {
                throw new InvalidArgumentException("The ability '{$ability->name}' of {$module->name()} is not a valid name, or core uses it.");
            }
            // An ability belongs to the area of its module, so a module cannot take a name that core or another uses.
            $this->abilities->register($ability->name, $ability->label, $ability->description, $module->name());
        }

        $module->slots($this->slots);
        $this->modules[$module->name()] = $module;
    }

    /**
     * @return array<string, Module>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * @return array<string, string> Why each module that was left out was.
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /**
     * The menu items and settings screens of every module, as the menu of the admin holds them.
     *
     * @return array<string, array<string, mixed>>
     */
    public function menu(): array
    {
        $others = lang('Admin.others');
        $menu   = [];

        foreach ($this->modules as $module) {
            foreach ($module->menu() as $entry) {
                $menu[$entry->section !== '' ? $entry->section : $others][$entry->key] = $entry->toArray();
            }
            foreach ($module->settings() as $screen) {
                $item = ['url' => $screen->url, 'text' => $screen->label, 'tooltip_content' => $screen->label, 'tooltip_placement' => 'right'];
                if ($screen->ability !== null) {
                    $item['ability'] = $screen->ability;
                }
                $menu[$others]['settings']['submenu'][$screen->key] = $item;
            }
        }

        return $menu;
    }

    /**
     * Ask every module for its routes.
     */
    public function routes(RouteCollection $routes): void
    {
        foreach ($this->modules as $module) {
            $module->routes(new ModuleRoutes($routes, $module->name()));
        }
    }

    /**
     * @return list<FieldType>
     */
    public function fieldTypes(): array
    {
        $types = [];
        foreach ($this->modules as $module) {
            foreach ($module->fieldTypes() as $type) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * The models the modules need.
     *
     * @return list<array{module: string, blueprint: Blueprint}>
     */
    public function blueprints(): array
    {
        $all = [];
        foreach ($this->modules as $name => $module) {
            foreach ($module->blueprints() as $blueprint) {
                $all[] = ['module' => $name, 'blueprint' => $blueprint];
            }
        }

        return $all;
    }

    /**
     * The model a person chose for a blueprint of a module, or null when none is chosen yet.
     */
    public function modelOf(string $module, string $key): ?int
    {
        $id = $this->settings->get('Modules.' . $module . '.' . $key);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * Keep the model chosen for a blueprint of a module.
     */
    public function useModel(string $module, string $key, int $modelId): void
    {
        $this->settings->set('Modules.' . $module . '.' . $key, $modelId);
    }

    /**
     * Whether a version fits a requirement such as `>=0.2.0`, `<1.0.0` or `*`. A pre-release counts as the release it
     * leads up to, so `0.2.0-beta.1` fits `>=0.2.0`.
     */
    public static function satisfies(string $version, string $requirement): bool
    {
        $requirement = trim($requirement);
        if ($requirement === '' || $requirement === '*') {
            return true;
        }
        if (preg_match('/^(>=|<=|>|<|=|==)?\s*([0-9][^\s]*)$/', $requirement, $m) !== 1) {
            return false;
        }

        // Compare the release part only, so a pre-release of 0.2.0 is not older than 0.2.0 for a module.
        $release = static fn (string $v): string => (string) preg_replace('/[-+].*$/', '', $v);

        return version_compare($release($version), $release($m[2]), $m[1] === '' ? '=' : $m[1]);
    }
}
