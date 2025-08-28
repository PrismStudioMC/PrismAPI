<?php

namespace PrismAPI\utils;

require_once __DIR__ . '/../../../vendor/autoload.php';

use function Opis\Closure\{serialize, unserialize};

class OpisSerializer
{
    /**
     * @param mixed $data
     * @return string
     */
    static public function serialize(mixed $data): string
    {
        return serialize($data);
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    static public function unserialize(mixed $data): mixed
    {
        return unserialize($data);
    }
}