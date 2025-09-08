<?php

namespace PrismAPI\types;

enum InputPermission: int
{
    case CAMERA = 1; // Look around
    case MOVEMENT = 2; // Move
    case LATERAL_MOVEMENT = 4; // Strafe
    case SNEAK = 5; // Crouch
    case JUMP = 6; // Jump
    case MOUNT = 7; // Mount / Ride
    case DISMOUNT = 8; // Dismount / Stop Riding
    case MOVE_FORWARD = 9; // Move forward
    case MOVE_BACKWARD = 10; // Move backward
    case MOVE_LEFT = 11; // Strafe left
    case MOVE_RIGHT = 12; // Strafe right

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }
}