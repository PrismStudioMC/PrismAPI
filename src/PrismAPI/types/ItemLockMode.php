<?php

namespace PrismAPI\types;

enum ItemLockMode: int
{
    case NONE = 0;
    case FULL = 1;
    case FULL_INVENTORY = 2;
}