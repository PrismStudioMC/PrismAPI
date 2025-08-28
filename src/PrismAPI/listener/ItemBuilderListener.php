<?php

namespace PrismAPI\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\StringTag;
use PrismAPI\item\ItemBuilder;
use PrismAPI\utils\OpisSerializer;

class ItemBuilderListener implements Listener
{
    /**
     * @param PlayerItemUseEvent $ev
     * @return void
     */
    public function onUse(PlayerItemUseEvent $ev): void
    {
        $player = $ev->getPlayer();

        $inventory = $player->getInventory();
        $slot = $inventory->getHeldItemIndex();

        $reflection = new \ReflectionClass(SimpleInventory::class);
        $property = $reflection->getProperty("slots");

        $slots = $property->getValue($inventory);

        $item = $slots[$slot] ?? VanillaItems::AIR();
        if ($item->isNull()) {
            return; // Empty hand
        }

        $namedTag = $item->getNamedTag();
        $tag = $namedTag->getTag(ItemBuilder::REFERENCE);
        if (!$tag instanceof ByteTag) {
            return; // Not a Prism item
        }

        $itemBuilder = $tag->getValue();
        if ($itemBuilder !== 1) {
            return; // Not a Prism item
        }

        $exclusive = $namedTag->getTag(ItemBuilder::EXCLUSIVE);
        if ($exclusive instanceof ByteTag && $exclusive->getValue() === 1) {
            $owner = $namedTag->getTag(ItemBuilder::OWNER);
            if ($owner === null || $owner->getValue() !== $player->getUniqueId()->toString()) {
                $item->pop($item->getCount());
                $ev->cancel();
                return; // Not the owner
            }
        }

        $condition = $namedTag->getTag(ItemBuilder::CONDITION);
        if ($condition instanceof StringTag) {
            $check = OpisSerializer::unserialize($condition->getValue());
            if (is_callable($check) && !$check($player, $item, $ev)) {
                $ev->cancel();
                return; // Condition not met
            }
        }

        $onUse = $namedTag->getTag(ItemBuilder::ON_USE);
        if ($onUse instanceof StringTag) {
            $callback = OpisSerializer::unserialize($onUse->getValue());
            if (is_callable($callback)) {
                $callback($player, $item, $ev);
                $ev->cancel();
                return;
            }
        }
    }

    /**
     * @param PlayerEntityInteractEvent $ev
     * @return void
     */
    public function onInteract(PlayerEntityInteractEvent $ev): void
    {
        $player = $ev->getPlayer();
        $entity = $ev->getEntity();

        $inventory = $player->getInventory();
        $slot = $inventory->getHeldItemIndex();

        $reflection = new \ReflectionClass(SimpleInventory::class);
        $property = $reflection->getProperty("slots");

        $slots = $property->getValue($inventory);

        $item = $slots[$slot] ?? VanillaItems::AIR();
        if ($item->isNull()) {
            return; // Empty hand
        }

        $namedTag = $item->getNamedTag();
        $tag = $namedTag->getTag(ItemBuilder::REFERENCE);
        if (!$tag instanceof ByteTag) {
            return; // Not a Prism item
        }

        $itemBuilder = $tag->getValue();
        if ($itemBuilder !== 1) {
            return; // Not a Prism item
        }

        $exclusive = $namedTag->getTag(ItemBuilder::EXCLUSIVE);
        if ($exclusive instanceof ByteTag && $exclusive->getValue() === 1) {
            $owner = $namedTag->getTag(ItemBuilder::OWNER);
            if ($owner === null || $owner->getValue() !== $player->getUniqueId()->toString()) {
                $item->pop($item->getCount());
                $ev->cancel();
                return; // Not the owner
            }
        }

        $condition = $namedTag->getTag(ItemBuilder::CONDITION);
        if ($condition instanceof StringTag) {
            $check = OpisSerializer::unserialize($condition->getValue());
            if (is_callable($check) && !$check($player, $item, $ev)) {
                $ev->cancel();
                return; // Condition not met
            }
        }

        $onInteract = $namedTag->getTag(ItemBuilder::ON_INTERACT);
        if ($onInteract instanceof StringTag) {
            $callback = OpisSerializer::unserialize($onInteract->getValue());
            if (is_callable($callback)) {
                $callback($player, $entity, $item, $ev);
                $ev->cancel();
                return;
            }
        }
    }

    /**
     * @param PlayerDropItemEvent $ev
     * @return void
     */
    public function onDrop(PlayerDropItemEvent $ev): void
    {
        $player = $ev->getPlayer();

        $inventory = $player->getInventory();
        $slot = $inventory->getHeldItemIndex();

        $reflection = new \ReflectionClass(SimpleInventory::class);
        $property = $reflection->getProperty("slots");

        $slots = $property->getValue($inventory);

        $item = $slots[$slot] ?? VanillaItems::AIR();
        if ($item->isNull()) {
            return; // Empty hand
        }

        $namedTag = $item->getNamedTag();
        $tag = $namedTag->getTag(ItemBuilder::REFERENCE);
        if (!$tag instanceof ByteTag) {
            return; // Not a Prism item
        }

        $itemBuilder = $tag->getValue();
        if ($itemBuilder !== 1) {
            return; // Not a Prism item
        }

        $exclusive = $namedTag->getTag(ItemBuilder::EXCLUSIVE);
        if ($exclusive instanceof ByteTag && $exclusive->getValue() === 1) {
            $owner = $namedTag->getTag(ItemBuilder::OWNER);
            if ($owner === null || $owner->getValue() !== $player->getUniqueId()->toString()) {
                $item->pop($item->getCount());
                $ev->cancel();
                return; // Not the owner
            }
        }

        $drop = $namedTag->getTag(ItemBuilder::DROP);
        if ($drop instanceof ByteTag && $drop->getValue() === 0) {
            $ev->cancel();
            return; // Drop not allowed
        }
    }
}