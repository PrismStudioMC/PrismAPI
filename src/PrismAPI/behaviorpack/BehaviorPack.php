<?php

namespace PrismAPI\behaviorpack;

use Ahc\Json\Comment as CommentedJsonDecoder;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\utils\Utils;
use PrismAPI\behaviorpack\parser\entities\BehaviorEntity;

class BehaviorPack implements ResourcePack
{
    protected string $path;
    protected Manifest $manifest;
    protected ?string $sha256 = null;

    /** @var resource */
    protected $fileResource;

    private array $entities = [];
    protected bool $scripts = false;

    /**
     * @param string $zipPath Path to the resource pack zip
     * @throws BehaviorPackException
     */
    public function __construct(string $zipPath)
    {
        $this->path = $zipPath;

        if (!file_exists($zipPath)) {
            throw new BehaviorPackException("File not found");
        }
        $size = filesize($zipPath);
        if ($size === false) {
            throw new BehaviorPackException("Unable to determine size of file");
        }
        if ($size === 0) {
            throw new BehaviorPackException("Empty file, probably corrupted");
        }

        $archive = new \ZipArchive();
        if (($openResult = $archive->open($zipPath)) !== true) {
            throw new BehaviorPackException("Encountered ZipArchive error code $openResult while trying to open $zipPath");
        }

        $manifestData = $this->parseManifest($archive);
        $this->entities = $this->parseEntities($archive);

        $archive->close();

        //maybe comments in the json, use stripped decoder (thanks mojang)
        try {
            $manifest = (new CommentedJsonDecoder())->decode($manifestData);
        } catch (\RuntimeException $e) {
            throw new BehaviorPackException("Failed to parse manifest.json: " . $e->getMessage(), 0, $e);
        }
        if (!($manifest instanceof \stdClass)) {
            throw new BehaviorPackException("manifest.json should contain a JSON object, not " . gettype($manifest));
        }

        $mapper = new \JsonMapper();
        $mapper->bExceptionOnMissingData = true;
        $mapper->bStrictObjectTypeChecking = true;

        try {
            /** @var Manifest $manifest */
            $manifest = $mapper->map($manifest, new Manifest());
        } catch (\JsonMapper_Exception $e) {
            throw new BehaviorPackException("Invalid manifest.json contents: " . $e->getMessage(), 0, $e);
        }

        $this->manifest = $manifest;
        $this->fileResource = fopen($zipPath, "rb");

        foreach ($manifest->modules as $module) {
            $type = strtolower($module->type);
            if ($type !== "script") {
                continue; // we only care about script modules
            }

            $language = strtolower($module->language);
            if ($language !== 'javascript') {
                throw new BehaviorPackException("Unsupported script module language: " . $module->language);
            }

            $entry = strtolower($module->entry);
            if (!preg_match('#^[a-z0-9_\-./]+$#', $entry)) {
                throw new BehaviorPackException("Invalid script module entry path: " . $module->entry);
            }

            $this->scripts = true;
        }
    }

    /**
     * Closes the file resource when the object is destroyed
     */
    public function __destruct()
    {
        fclose($this->fileResource);
    }

    /**
     * Retrieves the path to the resource pack file
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Retrieves the name of the resource pack
     *
     * @return string
     */
    public function getPackName(): string
    {
        return $this->manifest->header->name;
    }

    /**
     * Retrieves the version of the resource pack in "x.y.z" format
     *
     * @return string
     */
    public function getPackVersion(): string
    {
        return implode(".", $this->manifest->header->version);
    }

    /**
     * Retrieves the UUID of the resource pack
     *
     * @return string
     */
    public function getPackId(): string
    {
        return $this->manifest->header->uuid;
    }

    /**
     * Retrieves the size of the resource pack file in bytes
     *
     * @return int
     */
    public function getPackSize(): int
    {
        return filesize($this->path);
    }

    /**
     * Retrieves the SHA-256 hash of the resource pack file
     *
     * @param bool $cached
     * @return string
     */
    public function getSha256(bool $cached = true): string
    {
        if ($this->sha256 === null || !$cached) {
            $this->sha256 = hash_file("sha256", $this->path, true);
        }
        return $this->sha256;
    }

    /**
     * Retrieves a chunk of the resource pack file
     *
     * @param int $start
     * @param int $length
     * @return string
     */
    public function getPackChunk(int $start, int $length): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException("Pack length must be positive");
        }
        fseek($this->fileResource, $start);
        if (feof($this->fileResource)) {
            throw new \InvalidArgumentException("Requested a resource pack chunk with invalid start offset");
        }
        return Utils::assumeNotFalse(fread($this->fileResource, $length), "Already checked that we're not at EOF");
    }

    /**
     * Retrieves the manifest of the behavior pack
     *
     * @return Manifest
     */
    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    /**
     * Retrieves the file resource handle
     *
     * @return false|resource
     */
    public function getFileResource(): bool
    {
        return $this->fileResource;
    }

    /**
     * Checks if the behavior pack contains scripts
     *
     * @return bool
     */
    public function hasScripts(): bool
    {
        return $this->scripts;
    }

    /**
     * Sets whether the behavior pack contains scripts
     *
     * @param bool $scripts
     */
    public function setScripts(bool $scripts): void
    {
        $this->scripts = $scripts;
    }

    /**
     * Parses the manifest.json file from the zip archive
     *
     * @param \ZipArchive $archive
     * @return string
     */
    private function parseManifest(\ZipArchive $archive): string
    {
        if (($manifestData = $archive->getFromName("manifest.json")) === false) {
            $manifestPath = null;
            $manifestIdx = null;
            for ($i = 0; $i < $archive->numFiles; ++$i) {
                $name = Utils::assumeNotFalse($archive->getNameIndex($i), "This index should be valid");
                if (
                    ($manifestPath === null || strlen($name) < strlen($manifestPath)) &&
                    preg_match('#.*/manifest.json$#', $name) === 1
                ) {
                    $manifestPath = $name;
                    $manifestIdx = $i;
                }
            }
            if ($manifestIdx !== null) {
                $manifestData = $archive->getFromIndex($manifestIdx);
                assert($manifestData !== false);
            } elseif ($archive->locateName("pack_manifest.json") !== false) {
                throw new BehaviorPackException("Unsupported old pack format");
            } else {
                throw new BehaviorPackException("manifest.json not found in the archive root");
            }
        }

        return $manifestData;
    }

    /**
     * Scans the `entities/` directory inside the ZIP and returns parsed entities.
     *
     * - Iterates over all entries (ZIP "folders" may not exist as real entries).
     * - Filters by prefix `entities/`, skips directories, keeps only `.json` files.
     * - Reads entry data via index (fast), falls back to stream if needed.
     * - Decodes JSON with exceptions for clear error reporting.
     *
     * @param \ZipArchive $archive
     * @return list<BehaviorEntity>
     * @throws BehaviorPackException if an entity JSON is malformed or unreadable
     */
    private function parseEntities(\ZipArchive $archive): array
    {
        $prefix = 'entities/';
        $len = strlen($prefix);
        $entities = [];

        for ($i = 0, $n = $archive->numFiles; $i < $n; $i++) {
            $name = $archive->getNameIndex($i);
            if ($name === false) {
                continue; // defensive: invalid entry name
            }

            // Must be under entities/
            if (strncmp($name, $prefix, $len) !== 0) {
                continue;
            }

            // Skip directory entries (typically end with '/')
            if (str_ends_with($name, '/')) {
                continue;
            }

            // Keep only JSON files
            if (!str_ends_with(strtolower($name), '.json')) {
                continue;
            }

            // Read content (prefer index for speed; fallback to stream when needed)
            $raw = $archive->getFromIndex($i);
            if ($raw === false) {
                $fp = $archive->getStream($name);
                if (!$fp) {
                    throw new BehaviorPackException("Failed to read ZIP entry: {$name}");
                }
                $raw = stream_get_contents($fp);
                fclose($fp);
                if ($raw === false) {
                    throw new BehaviorPackException("Failed to read ZIP stream: {$name}");
                }
            }

            try {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new BehaviorPackException("Malformed entity JSON in '{$name}': " . $e->getMessage(), 0, $e);
            }

            $entities[] = new BehaviorEntity($data);
        }

        return $entities;
    }
}