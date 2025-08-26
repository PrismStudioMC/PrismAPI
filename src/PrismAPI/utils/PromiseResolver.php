<?php

namespace PrismAPI\utils;

final class PromiseResolver
{
    private ?bool $state = null;
    private mixed $result = null;

    /** @var callable[] */
    private array $onThen = [];
    /** @var callable[] */
    private array $onCatch = [];

    /**
     * @return bool
     */
    public function isResolved(): bool
    {
        return $this->state !== null;
    }

    /**
     * Resolves the promise with a given value.
     *
     * @param mixed $value
     * @return void
     */
    public function resolve(mixed $value): void
    {
        if($this->state !== null) {
            throw new \LogicException("Promise has already been resolved");
        }

        $this->state = true;
        $this->result = $value;

        foreach($this->onThen as $callback) {
            $callback($value);
        }
    }

    /**
     * Rejects the promise with a given reason.
     *
     * @param mixed $reason
     * @return void
     */
    public function reject(mixed $reason): void
    {
        if($this->state !== null) {
            throw new \LogicException("Promise has already been resolved");
        }

        $this->state = false;
        $this->result = $reason;

        foreach($this->onCatch as $callback) {
            $callback($reason);
        }
    }

    /**
     * Registers a callback to be called when the promise is resolved.
     *
     * @param callable $callback
     * @return void
     */
    public function then(callable $callback): void
    {
        if($this->state === true) {
            $callback($this->result);
        } else {
            $this->onThen[] = $callback;
        }
    }

    /**
     * Registers a callback to be called when the promise is rejected.
     *
     * @param callable $callback
     * @return void
     */
    public function catch(callable $callback): void
    {
        if($this->state === false) {
            $callback($this->result);
        } else {
            $this->onCatch[] = $callback;
        }
    }
}