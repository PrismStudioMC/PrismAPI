<?php

namespace PrismAPI\api;

use pocketmine\entity\Entity;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\SyncActorPropertyPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use PrismAPI\libs\muqsit\simplepackethandler\interceptor\IPacketInterceptor;
use PrismAPI\libs\muqsit\simplepackethandler\monitor\IPacketMonitor;
use PrismAPI\Loader;
use PrismAPI\types\EntitySyncProperties;

class EntityProperties
{
    use SingletonTrait;

    /** @var array<string, list<EntitySyncProperties>> */
    private array $properties = [];
    /** @var array<int, PropertySyncData> */
    private array $entityProperties = [];
    /** @var array<int, array<string, PropertySyncData>> */
    private array $clientProperties = [];
    /** @var array<string, array<string, array{0:int,1:EntitySyncProperties,2:bool,3:float|int,4:float|int}>> */
    private array $index = [];

    /** @var list<SyncActorPropertyPacket>|null */
    private ?array $packetsCache = null;
    private ?CompoundTag $playerNbtCache = null;

    private static ?\ReflectionProperty $rpInt = null, $rpFloat = null;

    public function __construct(
        private IPacketMonitor     $monitor,
        private IPacketInterceptor $interceptor
    )
    {
        self::setInstance($this);

        if (self::$rpInt === null) {
            $ref = new \ReflectionClass(PropertySyncData::class);
            self::$rpInt = $ref->getProperty('intProperties');
            self::$rpFloat = $ref->getProperty('floatProperties');
        }

        // Force player actor props on StartGame
        $this->monitor->monitorOutgoing(function (StartGamePacket $pk, NetworkSession $origin): void {
            $pk->playerActorProperties = new CacheableNbt($this->buildPlayerNBT());
        });

        // Ensure synced props are attached on SetActorData
        $this->monitor->monitorOutgoing(function (SetActorDataPacket $pk, NetworkSession $origin): void {
            // Skip if no synced properties registered for this entity type
            if (!empty($pk->metadata)) {
                $cloned = clone $pk;
                $cloned->metadata = [];
                $origin->sendDataPacket($cloned);
                return;
            }

            $player = $origin->getPlayer();
            if ($player === null) {
                return; // Player is not online, do not modify the packet
            }

            $entity = $player->getWorld()->getEntity($pk->actorRuntimeId);
            if ($entity === null) {
                return; // Entity not found, do not modify the packet
            }

            $props = $this->entityProperties[$pk->actorRuntimeId] ??= new PropertySyncData([], []);
            $clientProps = $this->clientProperties[$player->getId()][$entity::getNetworkTypeId()] ??= new PropertySyncData([], []);
            $pk->syncedProperties = new PropertySyncData(
                array_replace($props->getIntProperties(), $clientProps->getIntProperties()),
                array_replace($props->getFloatProperties(), $clientProps->getFloatProperties())
            );
        });

        // Clear entity props on entity removal
        $this->monitor->monitorOutgoing(function (ActorEventPacket $pk, NetworkSession $origin): void {
            if (isset($this->entityProperties[$pk->actorRuntimeId])) {
                unset($this->entityProperties[$pk->actorRuntimeId]);
            }
        });

        // After LOGIN_SUCCESS, push type property schemas
        $this->monitor->monitorOutgoing(function (PlayStatusPacket $pk, NetworkSession $origin): void {
            if ($pk->status === PlayStatusPacket::LOGIN_SUCCESS) {
                $origin->getBroadcaster()->broadcastPackets([$origin], $this->buildPackets());
            }
        });
    }

    /**
     * Registers a new entity sync property.
     *
     * @param string $entityId
     * @param EntitySyncProperties $prop
     * @return void
     */
    public function register(string $entityId, EntitySyncProperties $prop): void
    {
        $list = $this->properties[$entityId] ?? [];
        $list[] = $prop;
        $this->properties[$entityId] = $list;

        // build the property sync data
        $idx = \count($list) - 1;
        $min = $prop->getMin();
        $max = $prop->getMax();
        $this->index[$entityId][$prop->getPropertyName()] = [$idx, $prop, \is_float($min) || \is_float($max), $min, $max];

        // build the property sync data for the entity
        $this->packetsCache = null;
        if ($entityId === 'minecraft:player') $this->playerNbtCache = null;

        Loader::getInstance()->getLogger()->debug("Registered sync property '{$prop->getPropertyName()}' for entity '$entityId'.");
    }

    /**
     * Gets a client-side entity property. These properties are not synced with the server.
     *
     * @param Player $player
     * @param string $entityId
     * @param string $propertyName
     * @return int|float
     */
    public function getClientProperty(Player $player, string $entityId, string $propertyName): int|float
    {
        $meta = $this->index[$entityId][$propertyName] ?? null;
        if ($meta === null) {
            Loader::getInstance()->getLogger()->warning("Property '$propertyName' not found for entity '{$entityId}'.");
            return 0;
        }

        [$i, , $isFloat] = $meta;
        $props = $this->clientProperties[$player->getId()][$entityId] ??= new PropertySyncData([], []);
        return $isFloat ? ($props->getFloatProperties()[$i] ?? 0.0) : ($props->getIntProperties()[$i] ?? 0);
    }

    /**
     * Sets a client-side entity property. These properties are not synced with the server.
     *
     * @param Player $player
     * @param string $entityId
     * @param string $propertyName
     * @param int|float $value
     * @param bool $update
     * @return void
     */
    public function setClientProperty(Player $player, string $entityId, string $propertyName, int|float $value, bool $update = false): void
    {
        $meta = $this->index[$entityId][$propertyName] ?? null;
        if ($meta === null) {
            Loader::getInstance()->getLogger()->warning("Property '$propertyName' not found for entity '{$entityId}'.");
            return;
        }

        [$i, , $isFloat, $min, $max] = $meta;
        if ($value < $min || $value > $max) {
            Loader::getInstance()->getLogger()->warning("Value '$value' for '$propertyName' is out of bounds for entity '{$entityId}'.");
            return;
        }

        $props = $this->clientProperties[$player->getId()][$entityId] ??= new PropertySyncData([], []);
        $this->setPropertySyncData($isFloat, $props, $value, $i);

        // It could cause performance problems, but it's the simplest.
        if ($update) {
            if ($entityId === 'minecraft:player') {
                foreach ($player->getViewers() as $k => $viewer) {
                    $viewer->sendData([$player]); // force update for this player only
                }
            } else {
                // Force update all entities of this type for the player
                foreach ($player->getWorld()->getEntities() as $entity) {
                    if ($entity::getNetworkTypeId() === $entityId) {
                        $entity->sendData([$player]); // force update for this player only
                    }
                }
            }
        }

        Loader::getInstance()->getLogger()->debug("Set '$propertyName' to '$value' for entity '{$entityId}'.");
    }

    /**
     * Builds the packets for all registered entity properties.
     *
     * @param Entity $entity
     * @param string $propertyName
     * @return int|float
     */
    public function getProperty(Entity $entity, string $propertyName): int|float
    {
        $eid = $entity->getId();
        $type = $entity::getNetworkTypeId();

        $meta = $this->index[$type][$propertyName] ?? null;
        if ($meta === null) {
            Loader::getInstance()->getLogger()->warning("Property '$propertyName' not found for entity '{$entity::getNetworkTypeId()}' with ID '{$eid}'.");
            return 0;
        }
        [$i, , $isFloat] = $meta;

        $props = $this->entityProperties[$eid] ??= new PropertySyncData([], []);
        return $isFloat ? ($props->getFloatProperties()[$i] ?? 0.0) : ($props->getIntProperties()[$i] ?? 0);
    }

    /**
     * Define entity properties packets to be sent to the client.
     *
     * @param Entity $entity
     * @param string $propertyName
     * @param int|float $value
     * @return void
     */
    public function setProperty(Entity $entity, string $propertyName, int|float $value): void
    {
        $eid = $entity->getId();
        $type = $entity::getNetworkTypeId();

        $meta = $this->index[$type][$propertyName] ?? null;
        if ($meta === null) {
            Loader::getInstance()->getLogger()->warning("Property '$propertyName' not found for entity '{$type}' with ID '{$eid}'.");
            return;
        }
        [$i, , $isFloat, $min, $max] = $meta;

        if ($value < $min || $value > $max) {
            Loader::getInstance()->getLogger()->warning("Value '$value' for '$propertyName' is out of bounds for entity '{$type}' (ID {$eid}).");
            return;
        }

        $props = $this->entityProperties[$eid] ??= new PropertySyncData([], []);
        $this->setPropertySyncData($isFloat, $props, $value, $i);
        $entity->sendData(null); // force update

        Loader::getInstance()->getLogger()->debug("Set '$propertyName' to '$value' for entity '{$type}' (ID {$eid}).");
    }

    /**
     * @return array<SyncActorPropertyPacket>
     */
    private function buildPackets(): array
    {
        if ($this->packetsCache !== null) return $this->packetsCache;

        $out = [];
        foreach ($this->properties as $type => $schemaProps) {
            if (!$schemaProps) continue;
            $items = [];
            foreach ($schemaProps as $p) $items[] = $p->build();

            $nbt = CompoundTag::create()
                ->setTag('properties', new ListTag($items))
                ->setTag('type', new StringTag($type));

            $out[] = SyncActorPropertyPacket::create(new CacheableNbt($nbt));
        }
        return $this->packetsCache = $out;
    }

    /**
     * Builds the NBT for the player entity properties.
     *
     * This is used to send the player properties to the client on login.
     *
     * @return CompoundTag
     */
    private function buildPlayerNBT(): CompoundTag
    {
        if ($this->playerNbtCache !== null) return $this->playerNbtCache;
        $props = $this->properties['minecraft:player'] ?? [];
        if (!$props) return CompoundTag::create();

        $items = [];
        foreach ($props as $p) $items[] = $p->build();

        return $this->playerNbtCache = CompoundTag::create()
            ->setTag('properties', new ListTag($items))
            ->setTag('type', new StringTag('minecraft:player'));
    }

    /**
     * @param mixed $isFloat
     * @param PropertySyncData $props
     * @param float|int $value
     * @param mixed $i
     * @return void
     */
    private function setPropertySyncData(mixed $isFloat, PropertySyncData $props, float|int $value, mixed $i): void
    {
        if ($isFloat) {
            $arr = self::$rpFloat->getValue($props);
            $arr[$i] = (float)$value;
            self::$rpFloat->setValue($props, $arr);
        } else {
            $arr = self::$rpInt->getValue($props);
            $arr[$i] = (int)$value;
            self::$rpInt->setValue($props, $arr);
        }
    }
}