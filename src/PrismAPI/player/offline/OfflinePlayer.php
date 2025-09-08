<?php

namespace PrismAPI\player\offline;

use pocketmine\data\java\GameModeIdMap;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;

class OfflinePlayer extends Human
{
    public const TAG_FIRST_PLAYED = "firstPlayed"; //TAG_Long
    public const TAG_LAST_PLAYED = "lastPlayed"; //TAG_Long
    private const TAG_GAME_MODE = "playerGameType"; //TAG_Int
    private const TAG_SPAWN_WORLD = "SpawnLevel"; //TAG_String
    private const TAG_SPAWN_X = "SpawnX"; //TAG_Int
    private const TAG_SPAWN_Y = "SpawnY"; //TAG_Int
    private const TAG_SPAWN_Z = "SpawnZ"; //TAG_Int
    private const TAG_DEATH_WORLD = "DeathLevel"; //TAG_String
    private const TAG_DEATH_X = "DeathPositionX"; //TAG_Int
    private const TAG_DEATH_Y = "DeathPositionY"; //TAG_Int
    private const TAG_DEATH_Z = "DeathPositionZ"; //TAG_Int
    public const TAG_LEVEL = "Level"; //TAG_String
    public const TAG_LAST_KNOWN_XUID = "LastKnownXUID"; //TAG_String

    protected Server $server;
    private CompoundTag $nbt;

    private string $name;
    private string $xuid;

    protected int $firstPlayed;
    protected int $lastPlayed;
    protected GameMode $gamemode;

    private ?Position $spawnPosition = null;
    private ?Position $deathPosition = null;

    /**
     * @param string $name
     * @param CompoundTag $nbt
     */
    public function __construct(
        string      $name,
        CompoundTag $nbt,
    )
    {
        $this->name = $name;
        $this->xuid = $nbt->getString(self::TAG_LAST_KNOWN_XUID, "NONE");

        $this->server = Server::getInstance();
        $this->nbt = $nbt;

        $worldManager = $this->server->getWorldManager();

        if (($world = $worldManager->getWorldByName($this->nbt->getString(Player::TAG_LEVEL, ""))) !== null) {
            $location = EntityDataHelper::parseLocation($this->nbt, $world);
        } else {
            $world = $worldManager->getDefaultWorld();
            $location = Location::fromObject($world->getSpawnLocation(), $world);
        }

        parent::__construct($location, Human::parseSkinNBT($this->nbt), $this->nbt);
        $this->despawnFromAll();
        if ($this->location->isValid()) {
            $this->getWorld()->removeEntity($this);
        }

        $this->getInventory()->getListeners()->clear();
        $this->getArmorInventory()->getListeners()->clear();
        $this->getOffHandInventory()->getListeners()->clear();
        $this->getEnderInventory()->getListeners()->clear();
    }

    protected function initHumanData(CompoundTag $nbt): void
    {
        $this->setNameTag($this->name);
    }

    /**
     * @param CompoundTag $nbt
     * @return void
     */
    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->firstPlayed = $nbt->getLong(self::TAG_FIRST_PLAYED, $now = (int)(microtime(true) * 1000));
        $this->lastPlayed = $nbt->getLong(self::TAG_LAST_PLAYED, $now);

        if (!$this->server->getForceGamemode() && ($gameModeTag = $nbt->getTag(self::TAG_GAME_MODE)) instanceof IntTag) {
            $this->setGameMode(GameModeIdMap::getInstance()->fromId($gameModeTag->getValue()) ?? GameMode::SURVIVAL);
        } else {
            $this->setGameMode($this->server->getGamemode());
        }

        $this->setNameTagVisible();
        $this->setNameTagAlwaysVisible();
        $this->setCanClimb();

        if (($world = $this->server->getWorldManager()->getWorldByName($nbt->getString(self::TAG_SPAWN_WORLD, ""))) instanceof World) {
            $this->spawnPosition = new Position($nbt->getInt(self::TAG_SPAWN_X), $nbt->getInt(self::TAG_SPAWN_Y), $nbt->getInt(self::TAG_SPAWN_Z), $world);
        }
        if (($world = $this->server->getWorldManager()->getWorldByName($nbt->getString(self::TAG_DEATH_WORLD, ""))) instanceof World) {
            $this->deathPosition = new Position($nbt->getInt(self::TAG_DEATH_X), $nbt->getInt(self::TAG_DEATH_Y), $nbt->getInt(self::TAG_DEATH_Z), $world);
        }
    }

    /**
     * @return CompoundTag
     */
    public function getSaveData(): CompoundTag
    {
        $nbt = $this->saveNBT();

        $nbt->setString(self::TAG_LAST_KNOWN_XUID, $this->xuid);

        if ($this->location->isValid()) {
            $nbt->setString(self::TAG_LEVEL, $this->getWorld()->getFolderName());
        }

        if ($this->hasValidCustomSpawn()) {
            $spawn = $this->getSpawn();
            $nbt->setString(self::TAG_SPAWN_WORLD, $spawn->getWorld()->getFolderName());
            $nbt->setInt(self::TAG_SPAWN_X, $spawn->getFloorX());
            $nbt->setInt(self::TAG_SPAWN_Y, $spawn->getFloorY());
            $nbt->setInt(self::TAG_SPAWN_Z, $spawn->getFloorZ());
        }

        if ($this->deathPosition !== null && $this->deathPosition->isValid()) {
            $nbt->setString(self::TAG_DEATH_WORLD, $this->deathPosition->getWorld()->getFolderName());
            $nbt->setInt(self::TAG_DEATH_X, $this->deathPosition->getFloorX());
            $nbt->setInt(self::TAG_DEATH_Y, $this->deathPosition->getFloorY());
            $nbt->setInt(self::TAG_DEATH_Z, $this->deathPosition->getFloorZ());
        }

        $nbt->setInt(self::TAG_GAME_MODE, GameModeIdMap::getInstance()->toId($this->gamemode));
        $nbt->setLong(self::TAG_FIRST_PLAYED, $this->firstPlayed);
        return $nbt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getXuid(): string
    {
        return $this->xuid;
    }

    /**
     * @return int
     */
    public function getFirstPlayed(): int
    {
        return $this->firstPlayed;
    }

    /**
     * @return int
     */
    public function getLastPlayed(): int
    {
        return $this->lastPlayed;
    }

    /**
     * @return bool
     */
    public function hasPlayedBefore(): bool
    {
        return $this->lastPlayed - $this->firstPlayed > 1; // microtime(true) - microtime(true) may have less than one millisecond difference
    }

    /**
     * @return GameMode
     */
    public function getGamemode(): GameMode
    {
        return $this->gamemode;
    }

    /**
     * @param GameMode $gamemode
     */
    public function setGamemode(GameMode $gamemode): void
    {
        $this->gamemode = $gamemode;
    }

    /**
     * @return Position|null
     */
    public function getSpawn(): ?Position
    {
        return $this->spawnPosition;
    }

    /**
     * @return Position|null
     */
    public function getDeathPosition(): ?Position
    {
        return $this->deathPosition;
    }

    /**
     * @param Position|null $position
     */
    public function setSpawn(?Position $position): void
    {
        $this->spawnPosition = $position;
    }

    /**
     * @param Position|null $deathPosition
     */
    public function setDeathPosition(?Position $deathPosition): void
    {
        $this->deathPosition = $deathPosition;
    }

    /**
     * @return bool
     */
    public function hasValidCustomSpawn(): bool
    {
        return $this->spawnPosition !== null && $this->spawnPosition->isValid();
    }

    protected function onDispose(): void
    {
        // NOOP
    }
}