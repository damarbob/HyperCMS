<?php

namespace App\Hyper;

use JsonSerializable;

/**
 * Class HyperHook
 *
 * Represents a hook with a unique name, a human-readable label, and a description.
 * This class implements the JsonSerializable interface to provide a custom JSON
 * representation of the hook. Optionally, field aliases can be defined to alter
 * the JSON keys from the default property names.
 *
 * @package App\Hyper
 */
class HyperHook implements JsonSerializable
{
    /**
     * The unique name of the hook.
     *
     * @var string
     */
    private $name;

    /**
     * A human-readable label for the hook.
     *
     * @var string
     */
    private $label;

    /**
     * The description of what the hook does.
     *
     * @var string
     */
    private $description;

    /**
     * Optional field aliases for JSON serialization.
     * If defined, the keys in the JSON output will use the alias names
     * instead of the original property names.
     *
     * @var array
     */
    private static $fieldAliases = [];

    /**
     * Constructor for HyperHook.
     *
     * @param string $name        The unique name of the hook.
     * @param string $label       A human-readable label for the hook.
     * @param string $description A description for the hook.
     */
    public function __construct(string $name, string $label, string $description)
    {
        $this->name        = $name;
        $this->label       = $label;
        $this->description = $description;
    }

    /**
     * Get the name of the hook.
     *
     * @return string The unique name of the hook.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the label of the hook.
     *
     * @return string The human-readable label of the hook.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the description of the hook.
     *
     * @return string The hook's description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set or update the field aliases for JSON serialization.
     *
     * This static method lets you define the aliases for the class properties for JSON output.
     * The keys of the array should match the original field names, and the values will be used
     * as the alias in the JSON.
     *
     * @param array $aliases Associative array of field aliases (e.g., ['name' => 'hook_name']).
     */
    public static function setFieldAliases(array $aliases): void
    {
        self::$fieldAliases = $aliases;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * This method maps the object's properties to an associative array.
     * If field aliases have been defined in the static property, those will be used 
     * as the keys instead of the default property names.
     *
     * @return array The data to be serialized for JSON output.
     */
    public function jsonSerialize(): array
    {
        // Map the fields to their current values.
        $fields = [
            'name'        => $this->name,
            'label'       => $this->label,
            'description' => $this->description,
        ];

        // Use field aliases if defined, otherwise use the original key.
        $serialized = [];
        foreach ($fields as $key => $value) {
            $alias = self::$fieldAliases[$key] ?? $key;
            $serialized[$alias] = $value;
        }

        return $serialized;
    }
}
