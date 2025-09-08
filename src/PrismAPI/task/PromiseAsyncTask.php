<?php

namespace PrismAPI\task;

use pocketmine\network\mcpe\auth\ProcessLoginTask;
use pocketmine\scheduler\AsyncTask;
use PrismAPI\utils\AsyncRequest;
use PrismAPI\utils\OpisSerializer;
use PrismAPI\utils\PromiseAsyncResolver;
use PrismAPI\utils\PromiseResolver;

class PromiseAsyncTask extends AsyncTask
{
    private const TLS_KEY_RESOLVER = "resolver";
    private bool $failed = false;

    /**
     * @param string $callback
     * @param PromiseResolver $resolver
     */
    public function __construct(
        private readonly string $callback,
        PromiseResolver    $resolver
    )
    {
        $this->storeLocal(self::TLS_KEY_RESOLVER, $resolver);
    }

    public function onRun(): void
    {
        try {
            $deserialize = OpisSerializer::unserialize($this->callback);
            $this->setResult($deserialize());
        } catch (\Exception $e) {
            $this->failed = true;
            $this->setResult($e);
        }
    }

    /**
     * @return void
     */
    public function onCompletion(): void
    {
        /** @var PromiseAsyncResolver $resolver */
        $resolver = $this->fetchLocal(self::TLS_KEY_RESOLVER);

        if ($this->failed) {
            $resolver->reject($this->getResult());
            return;
        }

        $resolver->resolve($this->getResult());
    }
}