<?php

namespace PrismAPI\player;

use pocketmine\inventory\SimpleInventory;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use PrismAPI\listener\OfflinePlayerListener;
use PrismAPI\player\offline\OfflinePlayer;

class PlayerFactory
{
    use SingletonTrait;

    /** @var OfflinePlayer[] */
    private array $offlinePlayers = [];
    /** @var string[] */
    private array $xuidList = [];

    public function __construct()
    {
        self::setInstance($this);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function isLoadedOfflinePlayer(string $name): bool
    {
        return isset($this->offlinePlayers[strtolower($name)]);
    }

    /**
     * @param string $name
     * @return OfflinePlayer|null
     */
    public function getOfflinePlayer(string $name): ?OfflinePlayer
    {
        $k = strtolower($name);
        if (isset($this->offlinePlayers[$k])) {
            return $this->offlinePlayers[$k];
        }

        $nbt = Server::getInstance()->getOfflinePlayerData($name);
        if ($nbt === null) {
            // Unknown player
            return null;
        }

        return $this->offlinePlayers[$k] = new OfflinePlayer($name, $nbt);
    }

    /**
     * @param OfflinePlayer $player
     * @return void
     */
    public function removeOfflinePlayer(OfflinePlayer $player): void
    {
        $k = strtolower($player->getName());
        if (isset($this->offlinePlayers[$k])) {
            unset($this->offlinePlayers[$k]);
        }
        unset($this->xuidList[$player->getXUID()]);
        unset($player);
    }

    /**
     * @param string $name
     * @return OfflinePlayer|Player|null
     */
    public function getPlayer(string $name, bool $reqOnline = false): OfflinePlayer|Player|null
    {
        $player = Server::getInstance()->getPlayerExact($name);
        if ($player !== null) {
            return $player;
        }

        $offline = $this->getOfflinePlayer($name);
        if ($offline !== null && !$reqOnline) {
            return $offline;
        }

        return null;
    }

    /**
     * Saves all offline players data to disk.
     *
     * @return void
     */
    public function saveOfflinePlayers(): void
    {
        $server = Server::getInstance();

        foreach ($this->offlinePlayers as $name => $player) {
            // Save player data
            $server->saveOfflinePlayerData($name, $player->getSaveData());

            // Remove from memory if no listeners are open
            if (!$this->hasOpenOfflineListener($player)) {
                // No listeners are open, safe to unload
                $this->removeOfflinePlayer($player);
            }
        }
    }

    /**
     * Check if an offline player has any open inventory listeners.
     * @param OfflinePlayer $player
     * @return bool
     */
    private function hasOpenOfflineListener(OfflinePlayer $player): bool
    {
        foreach ($this->iterPlayerInventories($player) as $inv) {
            foreach ($inv->getListeners() as $listener) {
                if ($listener instanceof OfflinePlayerListener) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Iterate over all possible inventories of an offline player.
     * @param OfflinePlayer $player
     * @return iterable
     */
    private function iterPlayerInventories(OfflinePlayer $player): iterable
    {
        // Possible inventories
        $candidates = [
            $player->getInventory() ?? null,
            $player->getArmorInventory() ?? null,
            $player->getOffHandInventory() ?? null,
            $player->getEnderInventory() ?? null,
        ];

        foreach ($candidates as $inv) {
            if ($inv instanceof SimpleInventory) {
                yield $inv;
            }
        }
    }
}