<?php

namespace PrismAPI\utils;

use pocketmine\Server;
use PrismAPI\task\PromiseAsyncTask;

class AsyncRequest
{
    public static function create(\Closure $asyncClosure): PromiseResolver
    {
        $resolver = new PromiseResolver();

        $asyncPool = Server::getInstance()->getAsyncPool();
        $asyncPool->submitTask(new PromiseAsyncTask(
            OpisSerializer::serialize($asyncClosure),
            $resolver
        ));
        return $resolver;
    }
}