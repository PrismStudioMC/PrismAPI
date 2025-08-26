# Prism Studio – PrismAPI

PrismAPI is a comprehensive utility API for PocketMine-MP that provides a wide range of tools and functionality for server development. It's not just about behavior packs and entity properties – it's a complete toolkit for building powerful Minecraft server plugins.

## Features

### Core Utility Systems

PrismAPI provides several core utility systems that make plugin development easier and more powerful:

#### ItemFactory
Advanced item management with serialization, runtime ID conversion, and item locking capabilities:

```php
use PrismAPI\item\ItemFactory;

// Get runtime information about items
$legacyInfo = ItemFactory::LEGACY_INFO($item);
$runtimeId = ItemFactory::RUNTIME_ID($item);
$runtimeMeta = ItemFactory::RUNTIME_META($item);
$stringId = ItemFactory::RUNTIME_STRING_ID($item);

// Serialize and deserialize items
$serialized = ItemFactory::SERIALIZE($item);
$deserialized = ItemFactory::DESERIALIZE($serialized);

// Lock items with different modes
$lockedItem = ItemFactory::LOCK($item, ItemLockMode::FULL);

// Get crafting recipes for an item
$crafts = ItemFactory::CRAFTS($item, depth: 3);
```

#### BlockFactory
Comprehensive block management with runtime ID conversion and string ID mapping:

```php
use PrismAPI\block\BlockFactory;

// Create blocks from runtime string IDs
$block = BlockFactory::FROM_RUNTIME_STRING_ID("minecraft:stone");

// Get runtime string IDs from blocks
$stringId = BlockFactory::RUNTIME_STRING_ID_FROM($block);
```

#### PrismCommand
Enhanced command system with automatic overload building and parameter handling:

```php
use PrismAPI\utils\PrismCommand;

class MyCommand extends PrismCommand {
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
```

#### PromiseResolver
Asynchronous programming support with promise-based operations:

```php
use PrismAPI\utils\PromiseResolver;

$promise = new PromiseResolver();

$promise->then(function($result) {
    // Handle successful resolution
})->catch(function($error) {
    // Handle errors
});

// Resolve or reject the promise
$promise->resolve("Success!");
// or
$promise->reject("Error occurred");
```

### Behavior Pack System

PrismAPI implements a full behavior-pack manager so you can use custom behavior packs on your PocketMine-MP server.

#### How it works

1. **Auto-loading:** Behavior packs are automatically detected and loaded from the server’s `behavior_packs/` folder.
2. **Script handling:** Full support for JavaScript script modules included in behavior packs.
3. **Smart stacking:** Behavior packs are applied in a configurable priority order.
4. **Encryption keys:** Supports encrypted behavior packs with decryption keys.

#### Behavior Pack Configuration

Create a `behavior_packs.yml` file inside the `behavior_packs/` folder:

```yaml
behavior_stack:
  # Packs are applied from bottom to top
  # Higher packs override resources from lower packs
  - myBasicPack.zip
  - myAdvancedPack.zip
  - myCustomPack.zip
```

#### Behavior Pack Structure

A behavior pack must include:

* A `manifest.json` describing the pack and its modules
* Behavior files for entities, blocks, and items
* Optionally, JavaScript script modules

#### Example `manifest.json`

```json
{
  "format_version": 2,
  "header": {
    "name": "My Behavior Pack",
    "description": "Description of my pack",
    "uuid": "12345678-1234-1234-1234-123456789012",
    "version": [1, 0, 0],
    "min_engine_version": [1, 20, 0]
  },
  "modules": [
    {
      "type": "data",
      "uuid": "87654321-4321-4321-4321-210987654321",
      "version": [1, 0, 0]
    },
    {
      "type": "script",
      "language": "javascript",
      "uuid": "11111111-2222-3333-4444-555555555555",
      "entry": "scripts/main.js",
      "version": [1, 0, 0]
    }
  ]
}
```

### EntityProperties System

The EntityProperties system synchronizes custom properties between server entities and player clients.

#### Features

1. **Automatic sync:** Properties are synchronized automatically when entities update.
2. **Type support:** Integer and float properties are supported.
3. **Value validation:** Minimum and maximum bounds are enforced.
4. **Smart caching:** Packet caching boosts performance.

#### Defining `EntitySyncProperties`

```php
use PrismAPI\types\EntitySyncProperties;

// Create an integer property
$healthProperty = new EntitySyncProperties(
    "custom_health",
    100,   // default value
    0,     // min value
    200    // max value
);

// Create a float property
$speedProperty = new EntitySyncProperties(
    "movement_speed",
    1.0,   // default value
    0.1,   // min value
    5.0    // max value
);
```

#### Property Structure

Each property includes:

* **Name:** A unique identifier
* **Default value:** Initial value
* **Minimum value:** Lower bound
* **Maximum value:** Upper bound

#### Automatic Synchronization

The system automatically intercepts:

* `SetActorDataPacket` to synchronize properties
* `StartGamePacket` to set player properties
* Entity lifecycle events to clean up removed properties

## Developer API
### BehaviorPackManager

```php
use PrismAPI\api\BehaviorPackManager;

$manager = BehaviorPackManager::getInstance();
$behaviorPacks = $manager->getBehaviorPacks();
```

### EntityProperties

```php
use PrismAPI\api\EntityProperties;

$entityProps = EntityProperties::getInstance();
// Use the API methods to manage properties
```

## Error Handling

The plugin automatically handles:

* Corrupted or invalid behavior packs
* Manifest parsing errors
* Property synchronization issues

## Performance

* Cached synchronization packets
* Deferred loading of behavior packs
* Optimized property dispatch
* Memory-aware management
* Efficient item and block ID mapping
* Optimized command overload building
* Smart promise resolution caching

## Installation

1. Download the PrismAPI plugin.
2. Place the folder into your server’s `plugins/` directory.
3. Restart the server.

## Support

If you have questions or issues:

* Check the API documentation
* Inspect server logs for errors
* Ensure your behavior packs follow the expected format

## Compatibility

* **PocketMine-MP:** API 5.0.0+
* **PHP:** 8.0+
* **Behavior Packs:** Format version 2
* **Network Protocol:** Latest MCPE protocol support

## License
This project is licensed under the [GNU General Public License v3.0](LICENSE) – see the LICENSE file for details.