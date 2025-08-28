<?php

namespace PrismAPI\types;

enum TrimPattern : string
{
    case SENTRY     = 'sentry';
    case DUNE       = 'dune';
    case COAST      = 'coast';
    case WILD       = 'wild';
    case WARD       = 'ward';
    case EYE        = 'eye';
    case VEX        = 'vex';
    case TIDE       = 'tide';
    case SNOUT      = 'snout';
    case RIB        = 'rib';
    case SPIRE      = 'spire';
    case WAYFINDER  = 'wayfinder';
    case SHAPER     = 'shaper';
    case RAISER     = 'raiser';
    case HOST       = 'host';
    case SILENCE    = 'silence';

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