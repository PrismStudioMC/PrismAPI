<?php

namespace PrismAPI;

use pocketmine\event\EventPriority;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use PrismAPI\api\BehaviorPackManager;
use PrismAPI\api\EntityProperties;
use PrismAPI\libs\muqsit\simplepackethandler\interceptor\IPacketInterceptor;
use PrismAPI\libs\muqsit\simplepackethandler\monitor\IPacketMonitor;
use PrismAPI\libs\muqsit\simplepackethandler\SimplePacketHandler;
use PrismAPI\utils\PrismCommand;
use Symfony\Component\Filesystem\Path;

class Loader extends PluginBase
{
    use SingletonTrait;

    private IPacketMonitor $monitor;
    private IPacketInterceptor $interceptor;

    protected function onEnable(): void
    {
        self::setInstance($this);

        // Initialize the packet monitor and interceptor
        $this->monitor = SimplePacketHandler::createMonitor($this);
        $this->interceptor = SimplePacketHandler::createInterceptor($this, EventPriority::HIGHEST);

        // Register command interception for overloads
        $this->monitor->monitorOutgoing(function (AvailableCommandsPacket $packet, NetworkSession $origin): void {
            $player = $origin->getPlayer();
            if($player === null) {
                return; // Player is not online, do not modify the packet
            }

            $commands = Server::getInstance()->getCommandMap()->getCommands();
            foreach ($commands as $k => $cmd) {
                // Skip commands that are not instances of PrismCommand
                if(!$cmd instanceof PrismCommand) {
                    continue;
                }

                // Rebuild overloads for the command
                $data = $packet->commandData[$cmd->getLabel()];
                $packet->commandData[$cmd->getLabel()] = new CommandData(
                    $cmd->getName(),
                    $cmd->getDescription(),
                    $data->getFlags(),
                    $data->getPermission(),
                    $data->getAliases(),
                    $cmd->buildOverloads($packet->hardcodedEnums, $packet->softEnums, $packet->enumConstraints),
                    $data->getChainedSubCommandData()
                );
            }
        });

        new EntityProperties($this->monitor, $this->interceptor); // Initialize entity sync properties

        // Initialize behavior pack manager
        new BehaviorPackManager(
            $this->monitor,
            $this->interceptor,
            Path::join($this->getServer()->getDataPath(), "behavior_packs"),
            $this->getLogger()
        );
    }

    /**
     * @return IPacketMonitor
     */
    public function getMonitor(): IPacketMonitor
    {
        return $this->monitor;
    }

    /**
     * @return IPacketInterceptor
     */
    public function getInterceptor(): IPacketInterceptor
    {
        return $this->interceptor;
    }
}