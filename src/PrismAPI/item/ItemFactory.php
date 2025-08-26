<?php

namespace PrismAPI\item;

use InvalidArgumentException;
use pocketmine\crafting\CraftingRecipe;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\MetaWildcardRecipeIngredient;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\item\Item;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\Server;
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
 * @method static array CRAFTS(Item $item, int $depth)
 */
class ItemFactory
{
    private static bool $initialized = false;
    private static array $functions = [];
    private static array $mappings = [];

    public function __construct()
    {
        self::init();
    }

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
        self::setupMappings();
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
        self::register("deserialize", fn(string $serialized): Item => Item::nbtDeserialize((new LittleEndianNbtSerializer())->read(base64_decode($serialized))->mustGetCompoundTag())
        );

        self::register("lock", function (Item $item, ItemLockMode $mode = ItemLockMode::FULL): Item {
            $item->getNamedTag()->setByte("minecraft:item_lock", $mode->value);
            return $item;
        });

        self::register('crafts', function (Item $item, int $depth = 3): array {
            $stringId = self::RUNTIME_STRING_ID($item);
            return self::resolveCraft($stringId, $depth);
        });
    }

    /**
     * Resolves craft dependencies up to $maxDepth.
     * Depth 0 includes $root itself; children are depth+1.
     *
     * @param string $stringId
     * @param int $maxDepth
     * @return list<string>  // order of discovery, unique, root included
     */
    private static function resolveCraft(string $stringId, int $maxDepth): array
    {
        $maxDepth = max(0, $maxDepth);

        // visited set (O(1) membership), and ordered output
        $seen = [];
        $order = [];

        // queue of [id, depth] with pointer index (no array_shift cost)
        $queue = [[$stringId, 0]];
        for ($i = 0; isset($queue[$i]); $i++) {
            [$id, $d] = $queue[$i];

            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $order[] = $id;

            if ($d >= $maxDepth) {
                continue; // do not expand children beyond max depth
            }

            foreach (self::$mappings[$id] ?? [] as $child) {
                if (!isset($seen[$child])) {
                    $queue[] = [$child, $d + 1];
                }
            }
        }

        /** @var list<string> $order */
        return $order;
    }

    private static function setupMappings(): void
    {
        $server = Server::getInstance();
        $craftManager = $server->getCraftingManager();
        $data = $craftManager->getCraftingRecipeIndex();

        /** @var array<int, CraftingRecipe> $data */
        foreach ($data as $k => $recipe) {
            if (!$recipe instanceof ShapedRecipe) {
                continue; // Skip non-shaped recipes for now
            }

            $ingredients = $recipe->getIngredientList();
            if (count($ingredients) !== 1) {
                continue;
            }

            $ingredient = array_shift($ingredients);
            $itemId = null;
            if ($ingredient instanceof ExactRecipeIngredient) {
                $itemId = ItemFactory::RUNTIME_STRING_ID($ingredient->getItem());
            } elseif ($ingredient instanceof MetaWildcardRecipeIngredient) {
                $itemId = $ingredient->getItemId();
            } else {
                continue; // Unknown ingredient type, skip
            }

            if (is_null($itemId)) {
                continue; // Unable to determine item ID, skip
            }

            $results = $recipe->getResults();
            foreach ($results as $result) {
                $key1 = ItemFactory::RUNTIME_STRING_ID($result);
                $key2 = $itemId;

                if (!isset($mapping[$key1])) {
                    self::$mappings[$key1] = [];
                }

                if (!isset($mapping[$key2])) {
                    self::$mappings[$key2] = [];
                }

                self::$mappings[$key1][] = $itemId;
                self::$mappings[$key2][] = $key1;
            }
        }

        $server->getLogger()->notice("ItemFactory: Mapped " . count(self::$mappings) . " items with crafting recipes.");
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
        if (!self::$initialized) {
            self::init();
        }

        $upperName = mb_strtoupper($name);
        if (!isset(self::$functions[$upperName])) {
            throw new \InvalidArgumentException("No such registry member: " . self::class . "::" . $upperName);
        }

        return call_user_func_array(self::$functions[$upperName], $arguments);
    }
}