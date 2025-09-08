<?php

namespace PrismAPI\utils;

use PrismAPI\types\ReflectedClass;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

final class Reflection
{
    /** @var ReflectionProperty[] */
    private static array $propCache = [];
    /** @var ReflectionMethod[] */
    private static array $methCache = [];
    /** @var array<class-string, list<class-string>> */
    private static array $hierCache = [];
    /** @var array<class-string, array<string, ReflectionProperty>> */
    private static array $allPropsCache = [];

    /**
     * Returns the value of a property (private/protected ok) or $default if unknown.
     *
     * @param object $obj
     * @param string $prop
     * @param mixed|null $default
     * @return mixed
     * @throws ReflectionException
     */
    public static function getProp(object $obj, string $prop, mixed $default = null): mixed
    {
        $rp = self::resolveProperty($obj::class, $prop);
        if (!$rp) return $default;

        // static
        if ($rp->isStatic()) {
            return $rp->getValue();
        }

        // non-static
        if (method_exists($rp, 'isInitialized') && !$rp->isInitialized($obj)) {
            return $default;
        }
        return $rp->getValue($obj);
    }

    /**
     * Sets the value of a property (private/protected ok).
     *
     * @param object $obj
     * @param string $prop
     * @param mixed $value
     * @return void
     * @throws ReflectionException
     */
    public static function setProp(object $obj, string $prop, mixed $value): void
    {
        $rp = self::resolveProperty($obj::class, $prop);
        if (!$rp) {
            throw new RuntimeException("Property '$prop' not found in " . $obj::class . " hierarchy.");
        }

        if ($rp->isStatic()) {
            $rp->setValue($value);
        } else {
            $rp->setValue($obj, $value);
        }
    }

    /**
     * Indicates whether the property exists somewhere in the hierarchy (class/parents/traits).
     *
     * @param object|string $objOrClass
     * @param string $prop
     * @return bool
     * @throws ReflectionException
     */
    public static function hasProp(object|string $objOrClass, string $prop): bool
    {
        $class = is_object($objOrClass) ? $objOrClass::class : ltrim($objOrClass, '\\');
        return (bool) self::resolveProperty($class, $prop);
    }

    /**
     * Calls a method (private/protected ok) with the given arguments.
     *
     * @param object $obj
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws ReflectionException
     */
    public static function call(object $obj, string $method, mixed ...$args): mixed
    {
        $rm = self::resolveMethod($obj::class, $method);
        if (!$rm) {
            throw new RuntimeException("Method '$method' not found in " . $obj::class .  " hierarchy.");
        }

        if ($rm->isStatic()) {
            return $rm->invokeArgs(null, $args);
        }
        return $rm->invokeArgs($obj, $args);
    }

    /**
     * Indicates whether the method exists somewhere in the hierarchy (class/parents/traits).
     *
     * @param object|string $objOrClass
     * @param string $method
     * @return bool
     * @throws ReflectionException
     */
    public static function hasMethod(object|string $objOrClass, string $method): bool
    {
        $class = is_object($objOrClass) ? $objOrClass::class : ltrim($objOrClass, '\\');
        return (bool) self::resolveMethod($class, $method);
    }

    /**
     * Returns the class hierarchy (self → parent → parent...) as an array of class names.
     *
     * @param object|string $objOrClass
     * @return class-string[]
     * @throws ReflectionException
     */
    public static function getClassHierarchy(object|string $objOrClass): array
    {
        $class = is_object($objOrClass) ? $objOrClass::class : ltrim($objOrClass, '\\');
        return self::hierarchy($class);
    }

    /**
     * Cast/clone $src to another class by copying compatible properties.
     * - Instantiate the target without a constructor
     * - Private/protected copy (via reflection)
     * - Ignore static/readonly
     * - Respects types (union/intersection/nullable)
     *
     * @template T of object
     * @param object $src
     * @param class-string<T> $targetClass
     * @return object
     * @throws ReflectionException
     */
    public static function cast(object $src, string $targetClass): object
    {
        $targetClass = ltrim($targetClass, '\\');

        if (!is_subclass_of($targetClass, ReflectedClass::class)) {
            throw new RuntimeException("Target class {$targetClass} must extend " . ReflectedClass::class . " for link-cast.");
        }

        $rc = new \ReflectionClass($targetClass);
        /** @var ReflectedClass $dst */
        $dst = $rc->newInstanceWithoutConstructor();

        // Internal bind (without public exposure)
        $bind = (new \ReflectionClass(ReflectedClass::class))->getMethod('__bind');
        $bind->invoke($dst, $src);

        return $dst;
    }

    /**
     * Clears all caches (properties, methods, hierarchies).
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$propCache = self::$methCache = self::$hierCache = [];
    }

    /**
     * Clears caches related to a specific class (properties, methods, hierarchies).
     *
     * @param object|string $objOrClass
     * @return void
     */
    public static function forgetClass(object|string $objOrClass): void
    {
        $class = is_object($objOrClass) ? $objOrClass::class : ltrim($objOrClass, '\\');
        unset(self::$propCache[$class], self::$methCache[$class], self::$hierCache[$class]);
    }

    /**
     * Resolves a property in the hierarchy (class + parents + traits).
     *
     * @param string $class
     * @param string $prop
     * @return ReflectionProperty|null
     * @throws ReflectionException
     */
    private static function resolveProperty(string $class, string $prop): ?ReflectionProperty
    {
        $class = ltrim($class, '\\');

        // cache hit?
        if (isset(self::$propCache[$class][$prop])) {
            return self::$propCache[$class][$prop] ?? null;
        }

        // hierarchy path
        foreach (self::hierarchy($class) as $cls) {
            $rc = new ReflectionClass($cls);

            // directly declared property
            if ($rc->hasProperty($prop)) {
                $rp = $rc->getProperty($prop);
                return self::$propCache[$class][$prop] = $rp;
            }

            // properties derived from the traits used by the class
            foreach (self::allTraitsOf($rc) as $traitRC) {
                if ($traitRC->hasProperty($prop)) {
                    $rp = $traitRC->getProperty($prop);
                    return self::$propCache[$class][$prop] = $rp;
                }
            }
        }

        // not found: store null to avoid re-scanning
        return self::$propCache[$class][$prop] = null;
    }

    /**
     * Resolves a method in the hierarchy (class + parents + traits).
     *
     * @param string $class
     * @param string $method
     * @return ReflectionMethod|null
     * @throws ReflectionException
     */
    private static function resolveMethod(string $class, string $method): ?ReflectionMethod
    {
        $class = ltrim($class, '\\');

        if (isset(self::$methCache[$class][$method])) {
            return self::$methCache[$class][$method] ?? null;
        }

        $lname = strtolower($method);
        foreach (self::hierarchy($class) as $cls) {
            $rc = new ReflectionClass($cls);

            // directly declared method
            foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC
                | ReflectionMethod::IS_PROTECTED
                | ReflectionMethod::IS_PRIVATE
                | ReflectionMethod::IS_STATIC) as $m) {
                if (strtolower($m->getName()) === $lname) {
                    return self::$methCache[$class][$method] = $m;
                }
            }

            // methods via traits
            foreach (self::allTraitsOf($rc) as $traitRC) {
                foreach ($traitRC->getMethods() as $m) {
                    if (strtolower($m->getName()) === $lname) {
                        return self::$methCache[$class][$method] = $m;
                    }
                }
            }
        }

        return self::$methCache[$class][$method] = null;
    }

    /**
     * Returns the hierarchy of a class (itself + parents) as a list of class names.
     *
     * @param string $class
     * * @return list<class-string>
     * @throws ReflectionException
     */
    private static function hierarchy(string $class): array
    {
        $class = ltrim($class, '\\');
        if (isset(self::$hierCache[$class])) return self::$hierCache[$class];

        $list = [];
        $rc = new ReflectionClass($class);
        do {
            /** @var class-string $name */
            $name = $rc->getName();
            $list[] = $name;
            $rc = $rc->getParentClass();
        } while ($rc);

        return self::$hierCache[$class] = $list;
    }

    /**
     * Recursively retrieves all traits used by a class **and** its parents.
     *
     * @param ReflectionClass $rc
     * @return list<ReflectionClass>
     */
    private static function allTraitsOf(ReflectionClass $rc): array
    {
        $seen = [];
        $out  = [];

        $cursor = $rc;
        do { // for each class in the hierarchy
            foreach (self::traitsRecursive($cursor) as $trc) {
                $n = $trc->getName();
                if (isset($seen[$n])) continue;
                $seen[$n] = true;
                $out[] = $trc;
            }
            $cursor = $cursor->getParentClass();
        } while ($cursor);

        return $out;
    }

    /**
     * Recursively retrieves all traits used by a class (not parents), including nested traits.
     *
     * @param ReflectionClass $rc
     * @return list<ReflectionClass>
     */
    private static function traitsRecursive(ReflectionClass $rc): array
    {
        $out = [];
        foreach ($rc->getTraits() as $trait) {
            $out[] = $trait;

            // nested traits
            foreach ($trait->getTraits() as $sub) {
                $out[] = $sub;
            }
        }
        return $out;
    }

    /**
     * Lists all properties of the class (self + parents + nested traits), indexed by name.
     *
     * @param class-string $class
     * @return array<string, ReflectionProperty>
     * @throws ReflectionException
     */
    private static function propsOf(string $class): array
    {
        $class = ltrim($class, '\\');
        if (isset(self::$allPropsCache[$class])) return self::$allPropsCache[$class];

        $rc = new ReflectionClass($class);
        $props = [];
        $seen  = [];

        // browse the hierarchy (self + parents)
        for ($cur = $rc; $cur; $cur = $cur->getParentClass()) {
            // direct properties
            foreach ($cur->getProperties() as $p) {
                $n = $p->getName();
                if (isset($seen[$n])) continue;
                $seen[$n] = true;
                $props[$n] = $p;
            }

            // properties via traits
            foreach (self::traitsRecursive($cur) as $t) {
                foreach ($t->getProperties() as $p) {
                    $n = $p->getName();
                    if (isset($seen[$n])) continue;
                    $seen[$n] = true;
                    $props[$n] = $p;
                }
            }
        }

        return self::$allPropsCache[$class] = $props;
    }

    /**
     * Reads all **non-static** and **initialized** properties of the object.
     *
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    private static function readAllProps(object $obj): array
    {
        $out = [];
        foreach (self::propsOf($obj::class) as $name => $rp) {
            if ($rp->isStatic()) continue;
            if (method_exists($rp, 'isInitialized') && !$rp->isInitialized($obj)) continue;
            $out[$name] = $rp->getValue($obj);
        }
        return $out;
    }

    /**
     * Checks if a value matches the type declaration of a property.
     *
     * @param ReflectionProperty $rp
     * @param mixed $v
     * @return bool
     */
    private static function isTypeOk(ReflectionProperty $rp, mixed $v): bool
    {
        $t = $rp->getType();
        if (!$t) return true; // no type declared
        if ($v === null) return $t->allowsNull();

        if ($t instanceof \ReflectionUnionType) {
            foreach ($t->getTypes() as $x) {
                if ($x instanceof ReflectionNamedType && self::matchNamed($x, $v)) return true;
            }
            return false;
        }

        if ($t instanceof \ReflectionIntersectionType) {
            foreach ($t->getTypes() as $x) {
                if ($x instanceof ReflectionNamedType && !self::matchNamed($x, $v)) return false;
            }
            return true;
        }

        /** @var ReflectionNamedType $t */
        return self::matchNamed($t, $v);
    }

    /**
     * Exact match of a \ReflectionNamedType against a value.
     *
     * @param ReflectionNamedType $t
     * @param mixed $v
     * @return bool
     */
    private static function matchNamed(ReflectionNamedType $t, mixed $v): bool
    {
        $n = $t->getName();
        if ($n === 'mixed') return true;

        return match ($n) {
            'int'       => is_int($v),
            'float'     => is_float($v),
            'string'    => is_string($v),
            'bool'      => is_bool($v),
            'array'     => is_array($v),
            'object'    => is_object($v),
            'callable'  => is_callable($v),
            'iterable'  => is_iterable($v),
            'null'      => $v === null,
            default     => $v instanceof $n,
        };
    }
}