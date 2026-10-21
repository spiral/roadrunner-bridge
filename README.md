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

## gRPC code generation

`php app.php grpc:generate` compiles the proto files from the `grpc` config with [buf](https://buf.build).
It runs locally, with no network access, and needs three binaries:
`buf`, `protoc` (buf calls it for the PHP message classes) and `protoc-gen-php-grpc`.
Download them with [DLoad](https://github.com/php-internal/dload): add to `dload.xml`

```xml
<actions>
    <download software="buf" version="^1.50"/>
    <download software="protoc" version="^36.0"/>
    <download software="protoc-gen-php-grpc" version="^3.0"/>
</actions>
```

and run `vendor/bin/dload get`. Pick the protoc major version that matches your `google/protobuf` package
(5.36 → protoc 36.x).

```php
// app/config/grpc.php
return [
    // Binaries. Null looks for `<root>/<name>[.exe]`, then in PATH.
    'bufPath' => null,
    'protocPath' => null,
    'pluginPath' => null,

    // Your own buf.gen.yaml, used as is. Null renders a template with the binaries above.
    'bufTemplate' => null,

    // The buf input: all imports are resolved from it.
    'servicesBasePath' => directory('root') . 'proto',
    'services' => [
        directory('root') . 'proto/echo/echo.proto',
    ],

    'generatedPath' => directory('root') . 'generated',
    'namespace' => 'App',
    'generators' => [],
];
```

Each service is generated together with the other proto files in its directory and subdirectories.
Without `servicesBasePath`, every service directory is a separate input.

With `bufTemplate`, the template's `out` directories decide where the files go: `generatedPath` and `namespace`
do not relocate them, and `generators` receive the files created or updated under `generatedPath`.
Use it for managed mode, extra plugins or BSR dependencies (with your own `buf.yaml`).

### Upgrading from protoc (5.0)

- `binaryPath` is removed; use `pluginPath`. New optional keys: `bufPath`, `protocPath`, `bufTemplate`.
- Imports are resolved from `servicesBasePath` only, no longer from the directory of the importing file.
  Rewrite `import "b.proto"` in `foo/a.proto` as `import "foo/b.proto"`.
  Every file in `services` must be inside `servicesBasePath`.
- Download `buf`, `protoc` and `protoc-gen-php-grpc` with DLoad as shown above.
  `rr download-protoc-binary` is no longer used.
- Regenerated files differ from the protoc output only by the `json_name` field option that buf records
  in the field comments and the embedded descriptor; the JSON names stay the same.

## License:

MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information. Maintained by [Spiral Scout](https://spiralscout.com).
