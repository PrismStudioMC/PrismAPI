<?php

namespace PrismAPI\api;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackType;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\resourcepacks\ResourcePackException;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use PrismAPI\behaviorpack\BehaviorPack;
use PrismAPI\behaviorpack\BehaviorPackException;
use PrismAPI\behaviorpack\BehaviorPackStackEntry;
use PrismAPI\libs\muqsit\simplepackethandler\interceptor\IPacketInterceptor;
use PrismAPI\libs\muqsit\simplepackethandler\monitor\IPacketMonitor;
use PrismAPI\Loader;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Path;

class BehaviorPackManager
{
    use SingletonTrait;

    /** @var array<string, BehaviorPack> */
    private array $behaviorPacks = [];
    /** @var array<string, BehaviorPack> */
    private array $scriptBehaviorPacks = [];
    /** @var array<string, string> */
    private array $encryptionKeys = [];
    private bool $hasScripts = false;

    public function __construct(
        private IPacketMonitor     $monitor,
        private IPacketInterceptor $interceptor,
        private string             $path,
        private \Logger            $logger,
    )
    {
        self::setInstance($this);

        if (!file_exists($this->path)) {
            mkdir($this->path);
            $this->logger->debug("Behavior packs path $path does not exist, creating directory");
        } else if (!is_dir($this->path)) {
            throw new \RuntimeException("Behavior packs path $path is not a directory");
        }

        $behaviorPacksYml = Path::join($this->path, "behavior_packs.yml");
        if (!file_exists($behaviorPacksYml)) {
            $this->logger->debug("Behavior packs config $behaviorPacksYml does not exist, creating default config");
            copy(Path::join(Loader::getInstance()->getResourceFolder(), "behavior_packs.yml"), $behaviorPacksYml);
        }

        $behaviorPacksConfig = new Config($behaviorPacksYml, Config::YAML, []);
        $this->logger->info("Loading behavior packs...");

        $behaviorStack = $behaviorPacksConfig->get("behavior_stack", []);
        if (!is_array($behaviorStack)) {
            throw new \InvalidArgumentException("\"behavior_stack\" key should contain a list of behavior names");
        }

        $manager = Server::getInstance()->getResourcePackManager();
        foreach (Utils::promoteKeys($behaviorStack) as $pos => $behavior) {
            if (!is_string($behavior) && !is_int($behavior) && !is_float($behavior)) {
                $logger->critical("Found invalid entry in behavior pack list at offset $pos of type " . gettype($behavior));
                continue;
            }

            $behavior = (string)$behavior;
            try {
                $newBehavior = $this->loadPackFromPath(Path::join($this->path, $behavior));
                $scripts = $newBehavior->hasScripts();
                if($scripts) {
                    $this->logger->debug("Behavior pack \"$behavior\" contains scripts.");
                    $this->hasScripts = true; // If any behavior pack has scripts, we set this to true
                    $this->scriptBehaviorPacks[$newBehavior->getPackId()] = $newBehavior;
                }

                $index = strtolower($newBehavior->getPackId());
                if (!Uuid::isValid($index)) {
                    //TODO: we should use Uuid in ResourcePack interface directly but that would break BC
                    //for now we need to validate this here to make sure it doesn't cause crashes later on
                    throw new ResourcePackException("Invalid UUID ($index)");
                }

                $keyPath = Path::join($this->path, $behavior . ".key");
                if (file_exists($keyPath)) {
                    try {
                        $key = Filesystem::fileGetContents($keyPath);
                    } catch (\RuntimeException $e) {
                        throw new ResourcePackException("Could not read encryption key file: " . $e->getMessage(), 0, $e);
                    }
                    $key = rtrim($key, "\r\n");
                    if (strlen($key) !== 32) {
                        throw new ResourcePackException("Invalid encryption key length, must be exactly 32 bytes");
                    }
                    $this->encryptionKeys[$index] = $key;
                }

                $this->behaviorPacks[$index] = $newBehavior;
            } catch (BehaviorPackException $e) {
                $this->logger->critical("Could not load behavior pack \"$behavior\": " . $e->getMessage());
                continue;
            }
        }

        $manager->setResourceStack(array_merge($manager->getResourceStack(), $this->behaviorPacks));
        foreach ($this->encryptionKeys as $uuid => $key) {
            $manager->setPackEncryptionKey($uuid, $key);
        }

        $this->logger->info("Successfully Loaded " . count($this->behaviorPacks) . " behavior packs");
        if(count($this->scriptBehaviorPacks) > 0) {
            foreach ($this->scriptBehaviorPacks as $k => $pack) {

            }
        }
        if(count($this->behaviorPacks) > 0) {
            $this->registerListener();
        }
    }

    /**
     * @return void
     */
    private function registerListener(): void
    {
        // Monitor outgoing StartGamePacket to modify its experiments
        $this->monitor->monitorOutgoing(function (StartGamePacket $packet, NetworkSession $origin): void {
            $packet->levelSettings->experiments = new Experiments(array_merge(
                $packet->levelSettings->experiments->getExperiments(),
                ["gametest" => true] // Enable experiments required for behavior packs
            ), $packet->levelSettings->experiments->hasPreviouslyUsedExperiments());
        });

        // Monitor outgoing ResourcePacksInfoPacket to modify its properties
        $this->monitor->monitorOutgoing(function (ResourcePacksInfoPacket $packet, NetworkSession $origin): void {
            $packet->mustAccept = true; // Force clients to accept behavior packs
            $packet->hasAddons = true; // Indicate that there are addons (behavior packs)
            //$packet->hasScripts = $this->hasScripts; WHY ITS BROKEN MOJANG???

            $entries = [];
            foreach ($packet->resourcePackEntries as $k => $entry) {
                if(!isset($this->behaviorPacks[$entry->getContentId()])) {
                    $entries[] = $entry;
                    continue; // Only modify behavior packs
                }

                $entries[] = new ResourcePackInfoEntry(
                    $entry->getPackId(),
                    $entry->getVersion(),
                    $entry->getSizeBytes(),
                    $entry->getEncryptionKey(),
                    $entry->getSubPackName(),
                    $entry->getContentId(),
                    $this->behaviorPacks[$entry->getContentId()]->hasScripts(), // Mark as having scripts
                    true, // Mark as addon pack
                    false,
                    $entry->getCdnUrl()
                );
            }
            $packet->resourcePackEntries = $entries;
        });

        // Monitor outgoing ResourcePackDataInfoPacket to modify its packType
        $this->monitor->monitorOutgoing(function (ResourcePackDataInfoPacket $packet, NetworkSession $origin): void {
            if(isset($this->behaviorPacks[$packet->packId])) {
                $packet->packType = ResourcePackType::BEHAVIORS; // Set the pack type to behaviors
            }
        });

        // Monitor outgoing ResourcePackStackPacket to modify its stacks
        $this->monitor->monitorOutgoing(function (ResourcePackStackPacket $packet, NetworkSession $origin): void {
            $manager = Server::getInstance()->getResourcePackManager();

            $resourcePacks = array_values(array_filter(
                $manager->getResourceStack(),
                static fn($p) => !($p instanceof BehaviorPack) // we only want resource packs here
            ));
            $packet->resourcePackStack = array_map(
                static fn(ResourcePack $p) => new ResourcePackStackEntry($p->getPackId(), $p->getPackVersion(), ''),
                $resourcePacks
            );

            $packet->behaviorPackStack = array_map(
                static fn(BehaviorPack $p) => new BehaviorPackStackEntry($p->getPackId(), $p->getPackVersion(), ''),
                array_values($this->behaviorPacks)
            );
        });
    }

    /**
     * Loads a behavior pack from the specified path.
     *
     * @param string $packPath
     * @return BehaviorPack
     */
    private function loadPackFromPath(string $packPath): BehaviorPack
    {
        if (!file_exists($packPath)) {
            throw new BehaviorPackException("File or directory not found");
        }
        if (is_dir($packPath)) {
            throw new BehaviorPackException("Directory resource packs are unsupported");
        }

        //Detect the type of behavior pack.
        $info = new \SplFileInfo($packPath);
        switch ($info->getExtension()) {
            case "zip":
            case "mcpack":
                return new BehaviorPack($packPath);
        }

        throw new BehaviorPackException("Format not recognized");
    }

    /**
     * @return array
     */
    public function getBehaviorPacks(): array
    {
        return $this->behaviorPacks;
    }

    /**
     * @param array $behaviorPacks
     */
    public function setBehaviorPacks(array $behaviorPacks): void
    {
        $this->behaviorPacks = $behaviorPacks;
    }
}