<?php

namespace PrismAPI\player\offline\types;

use InvalidArgumentException;
use pocketmine\entity\Human;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryListener;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use PrismAPI\player\PlayerFactory;

final class OfflineInventoryListener implements InventoryListener
{
    private bool $syncing = false;

    public function __construct(
        private string   $owner,
        private Inventory $inventory1,
        private ?Inventory $inventory2
    ) {
        if ($this->inventory2 !== null && $this->inventory1->getSize() !== $this->inventory2->getSize()) {
            throw new InvalidArgumentException("Inventories must have the same size.");
        }
    }

    public function onSlotChange(Inventory $inventory, int $slot, Item $oldItem): void
    {
        // Skip if we are already syncing (to avoid loops)
        if ($this->syncing) return;

        // Ensure we have a valid linked inventory
        $this->ensureLinked();

        // Get the other inventory
        $other = $this->otherOf($inventory);

        // If no other inventory, nothing to do
        if ($other === null) return;

        // If slot is out of bounds, ignore
        if ($slot < 0 || $slot >= $other->getSize()) return;

        $this->syncing = true;

        // Fine copy: only write what changes
        $new = $inventory->getItem($slot);
        if (!$new->equalsExact($other->getItem($slot))) {
            $other->setItem($slot, $new);
        }

        // Done
        $this->syncing = false;
    }

    public function onContentChange(Inventory $inventory, array $oldContents): void
    {
        // Skip if we are already syncing (to avoid loops)
        if ($this->syncing) return;

        // Ensure we have a valid linked inventory
        $this->ensureLinked();

        // Get the other inventory
        $other = $this->otherOf($inventory);

        // If no other inventory, nothing to do
        if ($other === null) return;

        $this->syncing = true;

        // Fine copy: only write what changes
        $size = min($inventory->getSize(), $other->getSize());
        for ($i = 0; $i < $size; $i++) {
            $it = $inventory->getItem($i);
            if (!$it->equalsExact($other->getItem($i))) {
                $other->setItem($i, $it);
            }
        }

        // Done
        $this->syncing = false;
    }

    /**
     * Check that the mirror inventory is still valid, and attempt to link it if necessary.
     *
     * @return void
     */
    private function ensureLinked(): void
    {
        // If inv1 no longer exists, we detach and exit.
        if (!isset($this->inventory1)) {
            $this->detachMirror();
            return;
        }

        // If inv2 is still alive, nothing to do
        if ($this->inventory2 !== null && $this->isAlive($this->inventory2)) {
            // check size match
            if ($this->inventory1->getSize() === $this->inventory2->getSize()) {
                return;
            }

            // size mismatch : detach and try to resolve a new one
            $this->detachMirror();
        }

        // Try to resolve a new mirror from the owner
        $player = PlayerFactory::getInstance()->getOfflinePlayer($this->owner);
        if ($player === null) {
            // owner not found
            $this->detachMirror();
            return;
        }

        // Resolve the mirror inventory
        $mirror = $this->resolveMirrorInventory($player, $this->inventory1);
        if ($mirror === null) {
            // cannot resolve
            $this->detachMirror();
            return;
        }

        // Check size match
        if ($mirror->getSize() !== $this->inventory1->getSize()) {
            // size mismatch
            $this->detachMirror();
            return;
        }

        // All good: set the new mirror and register listener
        $this->inventory2 = $mirror;
        $this->inventory2->getListeners()->add($this);
    }

    /**
     * Returns the other inventory in the pair, or null if the given inventory is not part of this listener.
     *
     * @param Inventory $inv
     * @return Inventory|null
     */
    private function otherOf(Inventory $inv): ?Inventory
    {
        if ($inv === $this->inventory1) return $this->inventory2;
        if ($inv === $this->inventory2) return $this->inventory1;
        return null;
    }

    /**
     * Detach the current mirror inventory and unregister listener.
     *
     * @return void
     */
    private function detachMirror(): void
    {
        $this->inventory2?->getListeners()->remove($this);
        $this->inventory2 = null;
    }

    /**
     * Check if the inventory is "alive":
     *
     * @param Inventory $inv
     * @return bool
     */
    private function isAlive(Inventory $inv): bool
    {
        if (!method_exists($inv, 'getHolder')) {
            return true; // e.g. SimpleInventory offline -> OK
        }

        // Has a holder
        $holder = $inv->getHolder();
        if ($holder instanceof Player) {
            return $holder->isOnline() && !$holder->isClosed();
        }
        if ($holder instanceof Human) {
            return !$holder->isClosed();
        }

        return true;
    }

    /**
     * Resolve the mirror inventory from the player and the given inventory1.
     *
     * @param object $player
     * @param Inventory $inventory1
     * @return Inventory|null
     */
    private function resolveMirrorInventory(object $player, Inventory $inventory1): ?Inventory
    {
        return match (true) {
            $inventory1 instanceof PlayerInventory        => $player->getInventory(),
            $inventory1 instanceof ArmorInventory         => $player->getArmorInventory(),
            $inventory1 instanceof PlayerOffHandInventory => $player->getOffHandInventory(),
            $inventory1 instanceof PlayerEnderInventory   => $player->getEnderChestInventory(),
            default                                       => null,
        };
    }
}
