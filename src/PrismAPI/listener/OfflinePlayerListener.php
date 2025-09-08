<?php

namespace PrismAPI\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\world\WorldSaveEvent;
use pocketmine\inventory\SimpleInventory;
use pocketmine\Server;
use PrismAPI\player\offline\types\OfflineInventoryListener;
use PrismAPI\player\PlayerFactory;

class OfflinePlayerListener implements Listener
{
    /**
     * Handle player creation to ensure offline player data is saved.
     * @param PlayerPreLoginEvent $ev
     * @return void
     */
    public function handlePreLogin(PlayerPreLoginEvent $ev) : void
    {
        $playerInfo = $ev->getPlayerInfo();
        $name = $playerInfo->getUsername();

        $loaded = PlayerFactory::getInstance()->isLoadedOfflinePlayer($name);
        if(!$loaded) {
            return; // Player data not loaded, nothing to do
        }

        $factory = PlayerFactory::getInstance();
        $offlinePlayer = $factory->getOfflinePlayer($name);
        Server::getInstance()->saveOfflinePlayerData($name, $offlinePlayer->getSaveData()); // Save current offline data
    }

    /**
     * Handle player login to sync offline inventory with online inventory.
     * @param PlayerLoginEvent $event
     * @return void
     */
    public function handleLogin(PlayerLoginEvent $event) : void
    {
        $player = $event->getPlayer();
        $name = $player->getName();

        $loaded = PlayerFactory::getInstance()->isLoadedOfflinePlayer($name);
        if(!$loaded) {
            return; // Player data not loaded, nothing to do
        }

        // Player data is loaded, perform necessary actions
        $factory = PlayerFactory::getInstance();
        $offlinePlayer = $factory->getOfflinePlayer($name);

        $this->rsyncInventory($name, $offlinePlayer->getInventory(), $player->getInventory());
        $this->rsyncInventory($name, $offlinePlayer->getArmorInventory(), $player->getArmorInventory());
        $this->rsyncInventory($name, $offlinePlayer->getOffHandInventory(), $player->getOffHandInventory());
        $this->rsyncInventory($name, $offlinePlayer->getEnderInventory(), $player->getEnderInventory());

        $factory->removeOfflinePlayer($offlinePlayer); // Refresh the offline player data
    }

    /**
     * Rsync two inventories by adding listeners to sync changes between them.
     * @param string $owner
     * @param SimpleInventory $oldInventory
     * @param SimpleInventory $newInventory
     * @return void
     */
    private function rsyncInventory(string $owner, SimpleInventory $oldInventory, SimpleInventory $newInventory) : void
    {
        $listener = new OfflineInventoryListener($owner, $oldInventory, $newInventory);
        $oldInventory->getListeners()->add($listener); // Add listener to old inventory to sync changes to new inventory
        $newInventory->getListeners()->add($listener); // Add listener to new inventory to sync changes to old inventory

        $newInventory->setContents($oldInventory->getContents()); // Initial sync
    }

    /**
     * Save all offline players when the default world is saved.
     * @param WorldSaveEvent $ev
     * @return void
     */
    public function handleWorldSave(WorldSaveEvent $ev) : void
    {
        $world = $ev->getWorld();
        $default = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if($world->getFolderName() !== $default->getFolderName()) {
            return; // Not the default world, ignore
        }

        PlayerFactory::getInstance()->saveOfflinePlayers();
    }
}