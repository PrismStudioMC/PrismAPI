<?php

namespace PrismAPI\utils;

use pocketmine\command\Command;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandEnumConstraint;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;

abstract class PrismCommand extends Command
{
    /**
     * Builds the command overloads for the command.
     *
     * @param CommandEnum[] $hardcodedEnums
     * @param CommandEnum[] $softEnums
     * @param CommandEnumConstraint[] $enumConstraints
     * @return array
     */
    public function buildOverloads(array &$hardcodedEnums, array &$softEnums, array &$enumConstraints): array
    {
        return [
            new CommandOverload(chaining: false, parameters: [CommandParameter::standard("args", AvailableCommandsPacket::ARG_TYPE_RAWTEXT, 0, true)])
        ];
    }
}