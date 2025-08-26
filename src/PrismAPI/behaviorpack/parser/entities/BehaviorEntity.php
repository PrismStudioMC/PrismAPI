<?php

namespace PrismAPI\behaviorpack\parser\entities;

use PrismAPI\api\EntityProperties;
use PrismAPI\types\EntitySyncProperties;

class BehaviorEntity
{
    private string $identifier;
    private string $category;
    private bool $isSpawnable = true;
    private bool $isSummonable = true;

    /** @var array<EntitySyncProperties> */
    private array $properties = [];

    public function __construct(array $data)
    {
        if(!isset($data["format_version"])) {
            throw new \InvalidArgumentException("Missing format_version");
        }

        if(!is_string($data["format_version"])) {
            throw new \InvalidArgumentException("format_version must be a string");
        }

        if(!isset($data["minecraft:entity"])) {
            throw new \InvalidArgumentException("Missing minecraft:entity");
        }

        if(!is_array($data["minecraft:entity"])) {
            throw new \InvalidArgumentException("minecraft:entity must be an array");
        }

        $entityData = $data["minecraft:entity"];
        if(!isset($entityData["description"])) {
            throw new \InvalidArgumentException("Missing description");
        }

        if(!is_array($entityData["description"])) {
            throw new \InvalidArgumentException("description must be an array");
        }

        $description = $entityData["description"];
        $this->identifier = $description["identifier"] ?? throw new \InvalidArgumentException("Missing identifier");
        $this->category = $description["spawn_category"] ?? throw new \InvalidArgumentException("Missing spawn_category");
        $this->isSpawnable = $description["is_spawnable"] ?? true;
        $this->isSummonable = $description["is_summonable"] ?? true;

        $this->decodeProperties($description["properties"] ?? []);
        $this->initEntity($entityData);
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Add a property to the entity.
     *
     * @return EntitySyncProperties[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @param string $category
     */
    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    /**
     * Initialize the entity with additional data if needed.
     *
     * @param array $entityData
     * @return void
     */
    public function initEntity(array $entityData): void
    {
        if(!empty($this->properties)){
            $entityProperties = EntityProperties::getInstance();
            foreach ($this->properties as $k => $property) {
                $entityProperties->register($this->identifier, $property);
            }
        }
    }

    /**
     * Decode properties from the given array.
     * @param array $properties
     * @return void
     */
    private function decodeProperties(array $properties): void
    {
        foreach ($properties as $k => $property) {
            $default = $property["default"] ?? throw new \InvalidArgumentException("Missing default for property '$k'");

            // determine if the type is float or int
            $type = $property["type"] ?? throw new \InvalidArgumentException("Missing type for property '$k'");
            if(strtolower($type) !== "float" && strtolower($type) !== "int"){
                throw new \InvalidArgumentException("Invalid type '$type' for property '$k'. Must be 'float' or 'int'.");
            }

            $range = $property["range"] ?? throw new \InvalidArgumentException("Missing range for property '$k'");
            if(!is_array($range)) {
                throw new \InvalidArgumentException("Range must be an array for property '$k'");
            }

            if(empty($range)) {
                throw new \InvalidArgumentException("Range array cannot be empty for property '$k'");
            }

            $min = null;
            $max = null;

            foreach ($range as $k_ => $value) {
                if(!is_numeric($value)) {
                    throw new \InvalidArgumentException("Range values must be numeric for property '$k'");
                }

                if($min === null || $value < $min) {
                    $min = $value;
                }

                if($max === null || $value > $max) {
                    $max = $value;
                }

                if($value < $min) {
                    $min = $value;
                } else if($value > $max) {
                    $max = $value;
                }
            }

            if(is_null($min) || is_null($max)) {
                throw new \InvalidArgumentException("Range must have at least one numeric value for property '$k'");
            }

            $this->properties[] = new EntitySyncProperties(
                $k,
                $default,
                $min,
                $max
            );
        }
    }
}