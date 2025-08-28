<?php

namespace PrismAPI\types;

class Trim
{
    public function __construct(
        private readonly TrimMaterial $material,
        private readonly TrimPattern $pattern
    ) {}

    /**
     * @return TrimMaterial
     */
    public function getMaterial(): TrimMaterial
    {
        return $this->material;
    }

    /**
     * @return TrimPattern
     */
    public function getPattern(): TrimPattern
    {
        return $this->pattern;
    }
}