<?php

namespace PrismAPI\behaviorpack;

final class ManifestDependencyEntry{

    public string $uuid = "";
    public string $module_name = "";

    /**
     * @var mixed
     * @required
     */
    public mixed $version;
}
