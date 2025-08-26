<?php

namespace PrismAPI\behaviorpack;

use pocketmine\resourcepacks\json\ManifestHeader;
use pocketmine\resourcepacks\json\ManifestMetadata;


/**
 * Model for JsonMapper to represent resource pack manifest.json contents.
 */
final class Manifest
{
    /** @required */
    public int $format_version;

    /** @required */
    public ManifestHeader $header;

    /**
     * @var ManifestModuleEntry[]
     * @required
     */
    public array $modules;

    public ?ManifestMetadata $metadata = null;

    /** @var string[] */
    public ?array $capabilities = null;

    /** @var ManifestDependencyEntry[] */
    public ?array $dependencies = null;
}