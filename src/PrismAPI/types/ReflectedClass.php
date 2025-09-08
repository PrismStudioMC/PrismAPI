<?php

namespace PrismAPI\types;

use PrismAPI\utils\Reflection;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

abstract class ReflectedClass
{
    protected object $__ref;

    /**
     * Static mapping of classes to their sources for static method calls.
     * @var array <string, string> Mapping of classes to their respective sources (for static calls).
     */
    protected static array $__staticMap = [];

    /**
     * Bind the source (required).
     *
     * @param object $source
     * @return void
     */
    final protected function __bind(object $source): void
    {
        $this->__ref = $source;
        static::$__staticMap[static::class] = $source::class;
    }

    /**
     * Binds the source class for static calls (optional, see $__staticMap).
     *
     * @param string $sourceClass
     * @return void
     */
    public static function linkTo(string $sourceClass): void
    {
        static::$__staticMap[static::class] = ltrim($sourceClass, '\\');
    }

    /**
     * Get the source object.
     *
     * @return object
     */
    public function __source(): object
    {
        return $this->__ref;
    }

    /**
     * Get a property from the source object via reflection.
     *
     * @param string $name
     * @return mixed
     * @throws ReflectionException
     */
    public function __get(string $name): mixed
    {
        // First, we try on the source via reflection.
        if (Reflection::hasProp($this->__ref, $name)) {
            return Reflection::getProp($this->__ref, $name);
        }

        return null;
    }

    /**
     * Set a property on the source object via reflection.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws ReflectionException
     */
    public function __set(string $name, mixed $value): void
    {
        if (Reflection::hasProp($this->__ref, $name)) {
            Reflection::setProp($this->__ref, $name, $value);
            return;
        }

        $class = static::class;
        throw new RuntimeException("Cannot set undefined property {$class}::\${$name}");
    }

    /**
     * Check if a property is set (not null) on the source object.
     *
     * @param string $name
     * @return bool
     * @throws ReflectionException
     */
    public function __isset(string $name): bool
    {
        if (Reflection::hasProp($this->__ref, $name)) {
            return Reflection::getProp($this->__ref, $name, null) !== null;
        }

        return false;
    }

    public function __unset(string $name): void
    {
        // If the property exists on the source side, set it to null (simple behavior).
        if (Reflection::hasProp($this->__ref, $name)) {
            Reflection::setProp($this->__ref, $name, null);
            return;
        }

        // Otherwise: nothing
    }

    /**
     * Call a method on the source object via reflection.
     *
     * @param string $name
     * @param array $args
     * @return mixed
     * @throws ReflectionException
     */
    public function __call(string $name, array $args): mixed
    {
        // first try source-side instance method
        if (Reflection::hasMethod($this->__ref, $name)) {
            return Reflection::call($this->__ref, $name, ...$args);
        }

        // method not found
        $class = static::class;
        throw new RuntimeException("Call to undefined method {$class}::{$name}()");
    }

    /**
     * Call a static method on the linked source class via reflection.
     *
     * @param string $name
     * @param array $args
     * @return mixed
     * @throws ReflectionException
     */
    public static function __callStatic(string $name, array $args): mixed
    {
        $target = static::class;
        $srcClass = static::$__staticMap[$target] ?? null;
        if (!$srcClass) {
            throw new RuntimeException("No linked source class for static call on {$target}::{$name}()");
        }

        // Calling a static method on the source class via raw reflection
        $rc = new ReflectionClass($srcClass);
        $lname = strtolower($name);
        foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED | \ReflectionMethod::IS_PRIVATE | \ReflectionMethod::IS_STATIC) as $m) {
            if (strtolower($m->getName()) === $lname && $m->isStatic()) {
                return $m->invokeArgs(null, $args);
            }
        }

        throw new RuntimeException("Static method {$srcClass}::{$name}() not found or not static.");
    }

    /**
     * @return void
     */
    public function __clone()
    {
        // We clone the wrapper AND its source (deep wrapper)
        $this->__ref = clone $this->__ref;

        // Updates the static mapping for this class
        static::$__staticMap[static::class] = $this->__ref::class;
    }
}