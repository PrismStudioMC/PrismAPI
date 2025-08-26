<?php

namespace PrismAPI\types;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;

class EntitySyncProperties
{
    public function __construct(
        private string    $propertyName,
        private float|int $default = 0,
        private float|int $min = 0,
        private float|int $max = 1,
    )
    {
        if (empty($propertyName)) {
            throw new \InvalidArgumentException("Property name cannot be empty.");
        }

        if ($default < $min || $default > $max) {
            throw new \InvalidArgumentException("Default value must be within the range of min and max.");
        }

        if ($min > $max) {
            throw new \InvalidArgumentException("Minimum value cannot be greater than maximum value.");
        }

        if ($min < 0 || $max < 0) {
            throw new \InvalidArgumentException("Values must be non-negative.");
        }

        if (is_float($min) !== is_float($max)) {
            throw new \InvalidArgumentException("Both min and max must be of the same type (either both float or both int).");
        }
    }

    /**
     * @return string
     */
    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * @return float|int
     */
    public function getMin(): float|int
    {
        return $this->min;
    }

    /**
     * @return float|int
     */
    public function getMax(): float|int
    {
        return $this->max;
    }

    /**
     * Builds the NBT representation of the property.
     *
     * @return CompoundTag
     */
    public function build(): CompoundTag
    {
        $min = $this->getMin();
        $max = $this->getMax();
        $isFloat = is_float($min) || is_float($max);
        return CompoundTag::create()
            ->setTag("max", $isFloat ? new FloatTag((float)$max) : new IntTag((int)$max))
            ->setTag("min", $isFloat ? new FloatTag((float)$min) : new IntTag((int)$min))
            ->setTag("name", new StringTag($this->getPropertyName()))
            ->setTag("type", $isFloat ? new FloatTag((float)$this->default) : new IntTag((int)$this->default));
    }
}