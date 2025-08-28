<?php

namespace PrismAPI\entity;

use pocketmine\entity\EntityFactory as EntityFactoryPM;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\utils\SingletonTrait;
use PrismAPI\player\offline\OfflinePlayer;

class EntityFactory
{
    use SingletonTrait;

    public function __construct()
    {
        self::setInstance($this);
        $this->setup();
    }

    /**
     * @return void
     */
    private function setup(): void
    {
        $factory = EntityFactoryPM::getInstance();
        $reflectionClass = new \ReflectionClass(EntityFactoryPM::class);
        $property = $reflectionClass->getProperty("saveNames");

        $saveNames = $property->getValue($factory);
        $saveNames[OfflinePlayer::class] = reset($saveNames);
        $property->setValue($factory, $saveNames);
    }

    /**
     * Register a custom entity to the network so it can be summoned
     * @param string $identifier
     * @return void
     */
    public function networkRegister(string $identifier): void
    {
        $packet = StaticPacketCache::getInstance()->getAvailableActorIdentifiers();
        $tag = $packet->identifiers->getRoot();
        assert($tag instanceof CompoundTag);
        $id_list = $tag->getListTag("idlist");
        assert($id_list !== null);
        $id_list->push(CompoundTag::create()
            ->setString("bid", "")
            ->setByte("hasspawnegg", 0)
            ->setString("id", $identifier)
            ->setByte("summonable", 0)
        );
    }
}