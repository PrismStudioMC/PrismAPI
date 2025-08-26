<?php

namespace PrismAPI\block;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\Grass;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\item\StringToItemParser;

/**
 * @method static Block FROM_RUNTIME_STRING_ID(string $stringId)
 * @method static string RUNTIME_STRING_ID_FROM(Block $block)
 */
class BlockFactory
{
    private static bool $initialized = false;
    private static array $functions = [];
    private static array $stringIdMapping = [];
    private static array $blockMapping = [];

    /**
     * @param string $name
     * @return void
     */
    private static function verifyName(string $name): void
    {
        if (preg_match('/^(?!\d)[A-Za-z\d_]+$/u', $name) === 0) {
            throw new \InvalidArgumentException("Invalid member name \"$name\", should only contain letters, numbers and underscores, and must not start with a number");
        }
    }

    /**
     * @return void
     */
    private static function init(): void
    {
        self::$initialized = true;
        self::initializeMapping();
        self::setup();
    }

    /**
     * Inserts default entries into the registry.
     *
     * @return void
     */
    protected static function setup(): void
    {
        self::register("from_runtime_string_id", function (string $stringId): Block {
            $split = explode(":", $stringId, 2);
            if(count($split) > 2) {
                throw new InvalidArgumentException("Invalid runtime string ID: " . $stringId);
            }

            // If no namespace is given, assume "minecraft"
            if(count($split) > 1) {
                $stringId = $split[1];
            }

            // Handle old IDs
            if (!isset(self::$stringIdMapping[$stringId])) {
                throw new InvalidArgumentException("No block found for runtime string ID: " . $stringId);
            }
            return clone self::$stringIdMapping[$stringId];
        });

        self::register("runtime_string_id_from", function (Block $block): string {
            if(!isset(self::$blockMapping[$block->getTypeId()])) {
                throw new InvalidArgumentException("No runtime string ID found for block: " . $block->getName());
            }

            return self::$blockMapping[$block->getTypeId()];
        });
    }

    /**
     * Initialize the mapping between string IDs and Block instances.
     *
     * @return void
     */
    private static function initializeMapping(): void
    {
        $reflectionClass = new \ReflectionClass(RuntimeBlockStateRegistry::class);
        $typeIndexProperty = $reflectionClass->getProperty("typeIndex");

        $typeIndex = $typeIndexProperty->getValue(RuntimeBlockStateRegistry::getInstance());

        /** @var Block $block */
        foreach ($typeIndex as $typeId => $block) {
            $stringIds = StringToItemParser::getInstance()->lookupBlockAliases($block);

            // PMMP rename minecraft:grass to minecraft:grass_block
            // this is a hack
            if($block instanceof Grass) {
                $stringIds = array_merge(["grass_block"], $stringIds);
            }

            foreach ($stringIds as $stringId) {
                if (isset(self::$stringIdMapping[$stringId])) {
                    continue;
                }

                if(isset(self::$blockMapping[$typeId])) {
                    continue;
                }

                self::$stringIdMapping[$stringId] = clone $block;
                self::$blockMapping[$typeId] = $stringId;
            }
        }
    }

    /**
     * Register a new function to the ItemFactory.
     *
     * @param string $name
     * @param callable $func
     * @return void
     */
    private static function register(string $name, callable $func): void
    {
        self::verifyName($name);
        self::$functions[str_replace(" ", "_", mb_strtoupper($name))] = $func;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @phpstan-param list<mixed> $arguments
     * @return mixed
     *
     * @throws \ArgumentCountError
     * @throws \Error
     * @throws \InvalidArgumentException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if(!self::$initialized) {
            self::init();
        }

        $upperName = mb_strtoupper($name);
        if(!isset(self::$functions[$upperName])){
            throw new \InvalidArgumentException("No such registry member: " . self::class . "::" . $upperName);
        }

        return call_user_func_array(self::$functions[$upperName], $arguments);
    }
}