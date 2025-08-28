<?php

namespace PrismAPI\types;

enum TrimMaterial : string
{
    case QUARTZ    = 'quartz';
    case IRON      = 'iron';
    case COPPER    = 'copper';
    case GOLD      = 'gold';
    case LAPIS     = 'lapis';
    case EMERALD   = 'emerald';
    case DIAMOND   = 'diamond';
    case REDSTONE  = 'redstone';
    case AMETHYST  = 'amethyst';
    case NETHERITE = 'netherite';

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }
}