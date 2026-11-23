<?php

namespace App\Modules;

use App\Data\ModelStore;
use App\Data\Abilities;
use InvalidArgumentException;

/**
 * Finds the models that fit the blueprints of the modules and makes a model from a blueprint, so the screen of a module
 * can offer both and keep the choice.
 */
final class Blueprints
{
    public function __construct(private readonly ModelStore $models, private readonly Modules $modules)
    {
    }

    /**
     * The blueprint a module declared under a key.
     *
     * @throws InvalidArgumentException When the module has no such blueprint.
     */
    public function find(string $module, string $key): Blueprint
    {
        foreach ($this->modules->blueprints() as $entry) {
            if ($entry['module'] === $module && $entry['blueprint']->key === $key) {
                return $entry['blueprint'];
            }
        }

        throw new InvalidArgumentException("The module $module has no blueprint '$key'.");
    }

    /**
     * The live models whose fields fit a blueprint.
     *
     * @return list<array{id: int, name: string}>
     */
    public function fitting(Blueprint $blueprint): array
    {
        $found = [];
        foreach ($this->models->allArray() as $model) {
            $fields = json_decode((string) ($model['fields'] ?? ''), true);
            if (is_array($fields) && $blueprint->fits($fields)) {
                $found[] = ['id' => (int) $model['id'], 'name' => (string) $model['name']];
            }
        }

        return $found;
    }

    /**
     * Make a model from a blueprint and keep it as the choice of the module. Roles that hold every action on models of
     * the developers' kind get the same on this one, and a superadmin has everything anyway.
     *
     * @param string|null $group The group the model is listed under, or null for the name of the blueprint.
     */
    public function make(string $module, Blueprint $blueprint, int $userId, ?string $group = null): int
    {
        $id = $this->models->create([
            'name'        => $blueprint->label,
            'group'       => $group ?? $blueprint->label,
            'icon'        => $blueprint->icon,
            'fields'      => json_encode($blueprint->fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'label_field' => $blueprint->labelField,
            'access'      => ['developer' => Abilities::MODEL_ACTIONS],
        ], $userId);

        $this->modules->useModel($module, $blueprint->key, $id);

        return $id;
    }
}
