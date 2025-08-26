<?php

namespace PrismAPI\item;

use InvalidArgumentException;
use pocketmine\item\Item;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\convert\TypeConverter;
use PrismAPI\types\ItemLockMode;

/**
 * @method static array LEGACY_INFO(Item $item)
 * @method static int RUNTIME_ID(Item $item)
 * @method static int RUNTIME_META(Item $item)
 * @method static string RUNTIME_STRING_ID(Item $item)
 *
 * @method static string SERIALIZE(Item $item)
 * @method static Item DESERIALIZE(string $serialized)
 *
 * @method static Item LOCK(Item $item, ItemLockMode $mode = ItemLockMode::FULL)
 */
class ItemFactory
{
    private static bool $initialized = false;
    private static array $functions = [];

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
        self::setup();
    }

    protected static function setup(): void
    {
        self::register("legacy_info", function (Item $item): array {
            try {
                $networkItem = TypeConverter::getInstance()->getItemTranslator()->toNetworkId($item);
            } catch (\Throwable $e) {
                throw new InvalidArgumentException(
                    "Cannot convert item to network ID: " . $e->getMessage(), 0, $e
                );
            }

            return $networkItem;
        });

        self::register("runtime_id", function (Item $item): int {
            try {
                [$runtimeId] = self::LEGACY_INFO($item);
            } catch (\Throwable $e) {
                return 0;
            }

            return $runtimeId;
        });

        self::register("runtime_meta", function (Item $item): int {
            try {
                [, $runtimeMeta] = self::LEGACY_INFO($item);
            } catch (\Throwable $e) {
                return 0;
            }

            return $runtimeMeta;
        });

        self::register("runtime_string_id", function (Item $item): string {
            $runtimeId = self::RUNTIME_ID($item);
            try {
                $identifier = TypeConverter::getInstance()
                    ->getItemTypeDictionary()
                    ->fromIntId($runtimeId);
            } catch (\Throwable $e) {
                throw new InvalidArgumentException(
                    "Cannot convert item to string ID: " . $e->getMessage(), 0, $e
                );
            }

            return $identifier;
        });

        self::register("serialize", fn(Item $item): string => base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($item->nbtSerialize()))));
        self::register("deserialize", fn (string $serialized): Item =>
            Item::nbtDeserialize((new LittleEndianNbtSerializer())->read(base64_decode($serialized))->mustGetCompoundTag())
        );

        self::register("lock", function(Item $item, ItemLockMode $mode = ItemLockMode::FULL): Item  {
            $item->getNamedTag()->setByte("minecraft:item_lock", $mode->value);
            return $item;
        });
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