<?php

namespace PrismAPI\behaviorpack;

final class ManifestModuleEntry{

    public string $description;

    /** @required */
    public string $type;

    /** @required */
    public string $uuid;

    public string $language = ''; // Optional
    public string $entry = ''; // Optional

    /**
     * @var int[]
     * @phpstan-var array{int, int, int}
     * @required
     */
    public array $version;
}
