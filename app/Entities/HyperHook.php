<?php

namespace App\Entities;

use JsonSerializable;

class HyperHook implements JsonSerializable
{
    private $name;
    private $label;
    private $description;

    public function __construct(string $name, string $label, string $description)
    {
        $this->name = $name;
        $this->label = $label;
        $this->description = $description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Serialize the object to JSON using aliases if defined.
     *
     * @return array The serialized representation of the object.
     */
    public function jsonSerialize(): array
    {
        // Map the fields to their aliases (if aliases exist)
        $fields = [
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
        ];

        $serialized = [];
        foreach ($fields as $key => $value) {
            $alias = self::$fieldAliases[$key] ?? $key; // Use alias if it exists, otherwise the original field name
            $serialized[$alias] = $value;
        }

        return $serialized;
    }
}
