# RoadRunner bridge to Spiral Framework

[![PHP Version Require](https://poser.pugx.org/spiral/roadrunner-bridge/require/php)](https://packagist.org/packages/spiral/roadrunner-bridge)
[![Latest Stable Version](https://poser.pugx.org/spiral/roadrunner-bridge/v/stable)](https://packagist.org/packages/spiral/roadrunner-bridge)
[![phpunit](https://github.com/spiral/roadrunner-bridge/actions/workflows/phpunit.yml/badge.svg)](https://github.com/spiral/roadrunner-bridge/actions)
[![psalm](https://github.com/spiral/roadrunner-bridge/actions/workflows/psalm.yml/badge.svg)](https://github.com/spiral/roadrunner-bridge/actions)
[![Codecov](https://codecov.io/gh/spiral/roadrunner-bridge/branch/master/graph/badge.svg)](https://codecov.io/gh/spiral/roadrunner-bridge/)
[![Total Downloads](https://poser.pugx.org/spiral/roadrunner-bridge/downloads)](https://packagist.org/packages/spiral/roadrunner-bridge)
<a href="https://discord.gg/8bZsjYhVVk"><img src="https://img.shields.io/badge/discord-chat-magenta.svg"></a>

## Requirements

Make sure that your server is configured with following PHP version and extensions:

- PHP 8.2+
- Spiral Framework 3.14+
- Extension `protobuf` (recommended)

## Installation

To install the package:

```bash
composer require spiral/roadrunner-bridge
```

After package install you need to add bootloaders from the package in your application on the top of the list.

```php
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;

protected const LOAD = [
    RoadRunnerBridge\HttpBootloader::class, // Optional, if it needs to work with http plugin
    RoadRunnerBridge\QueueBootloader::class, // Optional, if it needs to work with jobs plugin
    RoadRunnerBridge\CacheBootloader::class, // Optional, if it needs to work with KV plugin
    RoadRunnerBridge\GRPCBootloader::class, // Optional, if it needs to work with GRPC plugin
    RoadRunnerBridge\CentrifugoBootloader::class, // Optional, if it needs to work with centrifugo server
    RoadRunnerBridge\MetricsBootloader::class, // Optional, if it needs to work with metrics plugin
    RoadRunnerBridge\LoggerBootloader::class, // Optional, if it needs to work with app-logger plugin
    RoadRunnerBridge\LockBootloader::class, // Optional, if it needs to work with lock plugin
    RoadRunnerBridge\ScaffolderBootloader::class, // Optional, to generate Centrifugo handlers via Scaffolder
    RoadRunnerBridge\CommandBootloader::class,
    // ...
];
```

## Usage

- [Cache](https://spiral.dev/docs/basics-cache)
- [Queue](https://spiral.dev/docs/queue-configuration)
- [GRPC](https://spiral.dev/docs/grpc-configuration)
- [Websockets](https://spiral.dev/docs/websockets-configuration)
- [Logger](https://spiral.dev/docs/basics-logging/#roadrunner-handler)
- [Metrics](https://spiral.dev/docs/advanced-prometheus-metrics)

> **Note**
> Read more about RoadRunner configuration on official site https://roadrunner.dev.

## License:

MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information. Maintained by [Spiral Scout](https://spiralscout.com).
