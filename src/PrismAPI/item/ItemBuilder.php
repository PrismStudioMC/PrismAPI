<?php

namespace PrismAPI\item;

use Closure;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\player\Player;
use PrismAPI\types\ItemLockMode;
use PrismAPI\types\Trim;
use PrismAPI\utils\OpisSerializer;

class ItemBuilder
{
    public const REFERENCE = "prism:itemBuilder";
    public const EXCLUSIVE = "prism:exclusive";
    public const OWNER = "prism:owner";
    public const ON_USE = "prism:onUse";
    public const ON_INTERACT = "prism:onInteract";
    public const DROP = "prism:drop";
    public const CONDITION = "prism:condition";

    private string $customName;
    private array $lore;
    private Closure $onUse;
    private Closure $onInteract;
    private Closure $condition;
    private Player $owner;
    private bool $exclusive;
    private bool $drop;
    private ItemLockMode $lockMode;
    private Trim $trim;
    /** @var Tag[] */
    private array $tags = [];

    /**
     * @param Item $item
     */
    private function __construct(
        private Item $item
    ) {}

    /**
     * @param Item $item
     * @return ItemBuilder
     */
    public static function create(Item $item): ItemBuilder
    {
        return new self($item);
    }

    /**
     * Sets the custom name of the item.
     * @param string $name
     * @return $this
     */
    public function setCustomName(string $name): self
    {
        $this->customName = $name;
        return $this;
    }

    /**
     * Sets the lore of the item.
     * @param array $lore
     * @return $this
     */
    public function setLore(array $lore): self
    {
        $this->lore = $lore;
        return $this;
    }

    /**
     * Sets the on use callback of the item.
     * @param Closure $callback
     * @return $this
     */
    public function onUse(Closure $callback): self
    {
        $this->onUse = $callback;
        return $this;
    }

    /**
     * Sets the on interact callback of the item.
     * @param Closure $callback
     * @return $this
     */
    public function onInteract(Closure $callback): self
    {
        $this->onInteract = $callback;
        return $this;
    }

    /**
     * Sets the condition callback of the item.
     * @param Closure $callback
     * @return $this
     */
    public function condition(Closure $callback): self
    {
        $this->condition = $callback;
        return $this;
    }

    /**
     * Sets the owner of the item.
     * @param Player $player
     * @return $this
     */
    public function setOwner(Player $player): self
    {
        $this->owner = $player;
        return $this;
    }

    /**
     * Sets whether the item is exclusive to the owner.
     * @param bool $exclusive
     * @return $this
     */
    public function setExclusive(bool $exclusive = true): self
    {
        $this->exclusive = $exclusive;
        return $this;
    }

    /**
     * Sets whether the item can be dropped.
     * @param bool $drop
     * @return $this
     */
    public function setDrop(bool $drop = true): self
    {
        $this->drop = $drop;
        return $this;
    }

    /**
     * Sets the lock mode of the item.
     * @param ItemLockMode $mode
     * @return $this
     */
    public function setLockMode(ItemLockMode $mode): self
    {
        $this->lockMode = $mode;
        return $this;
    }

    /**
     * Sets the trim of the item.
     * @param Trim $trim
     * @return $this
     */
    public function setTrim(Trim $trim): self
    {
        $this->trim = $trim;
        return $this;
    }

    /**
     * Sets a custom NBT tag on the item.
     * @param string $name
     * @param Tag $tag
     * @return $this
     */
    public function setTag(string $name, Tag $tag): self
    {
        $this->tags[$name] = $tag;
        return $this;
    }

    /**
     * Builds the item with the specified properties.
     * @return Item
     */
    public function build(): Item
    {
        $item = clone $this->item;
        $namedTag = $item->getNamedTag();
        if (isset($this->customName)) {
            $item->setCustomName($this->customName);
        }
        if (isset($this->lore)) {
            $item->setLore($this->lore);
        }

        if (isset($this->onUse)) {
            $namedTag->setString(self::ON_USE, OpisSerializer::serialize($this->onUse));
        }

        if (isset($this->onInteract)) {
            $namedTag->setString(self::ON_INTERACT, OpisSerializer::serialize($this->onInteract));
        }

        if (isset($this->condition)) {
            $namedTag->setString(self::CONDITION, OpisSerializer::serialize($this->condition));
        }

        if (isset($this->owner)) {
            $namedTag->setString(self::OWNER, $this->owner->getUniqueId()->toString());
        }

        if (isset($this->exclusive)) {
            $namedTag->setByte(self::EXCLUSIVE, $this->exclusive ? 1 : 0);
        }

        if (isset($this->drop)) {
            $namedTag->setByte(self::DROP, $this->drop ? 1 : 0);
        }

        if (isset($this->lockMode)) {
            ItemFactory::LOCK($item, $this->lockMode);
        }

        if (isset($this->trim)) {
            $namedTag->setTag("Trim",
                CompoundTag::create()
                ->setTag("Material", new StringTag($this->trim->getMaterial()->getValue()))
                ->setTag("Pattern", new StringTag($this->trim->getPattern()->getValue()))
            );
        }

        foreach ($this->tags as $name => $tag) {
            $namedTag->setTag($name, $tag);
        }

        $namedTag->setByte("prism:itemBuilder", 1);
        return $item;
    }
}