<p align="center">
    <a href="https://spiral.dev"><img alt="Spiral Framework" src="https://user-images.githubusercontent.com/773481/228578125-8e0ad713-905f-4450-a473-5839bd650472.png" style="width: 6in; display: block"></a>
</p>

<p align="center">RoadRunner integration for Spiral Framework</p>

<div align="center">

[![Documentation](https://img.shields.io/badge/Documentation-blue?style=for-the-badge&logo=gitbook&logoColor=white)](https://spiral.dev/docs/packages-roadrunner-bridge)
[![Sponsor](https://img.shields.io/static/v1?style=for-the-badge&label=&message=Sponsor&logo=githubsponsors&logoColor=white&color=%23EA4AAA)](https://github.com/sponsors/spiral)

[![Psalm Level](https://shepherd.dev/github/spiral/roadrunner-bridge/level.svg)](https://shepherd.dev/github/spiral/roadrunner-bridge)
[![Type Coverage](https://shepherd.dev/github/spiral/roadrunner-bridge/coverage.svg)](https://shepherd.dev/github/spiral/roadrunner-bridge)

</div>

<br />

This package runs a Spiral application on [RoadRunner](https://roadrunner.dev) and connects it to the RoadRunner plugins:
HTTP, Jobs, KV, gRPC, Centrifugo, Metrics, Logger and Lock.

## Get Started

### Installation

```bash
composer require spiral/roadrunner-bridge
```

[![PHP](https://img.shields.io/packagist/php-v/spiral/roadrunner-bridge.svg?style=flat-square&logo=php)](https://packagist.org/packages/spiral/roadrunner-bridge)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/spiral/roadrunner-bridge.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/spiral/roadrunner-bridge)
[![License](https://img.shields.io/packagist/l/spiral/roadrunner-bridge.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/spiral/roadrunner-bridge.svg?style=flat-square)](https://packagist.org/packages/spiral/roadrunner-bridge/stats)

Requirements:

- PHP 8.2+
- Spiral Framework 3.14+
- RoadRunner 3
- Extension `protobuf` (recommended)

### Configuration

Add the bootloaders you need at the top of the application's bootloader list:

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

### Usage

- [Cache](https://spiral.dev/docs/basics-cache)
- [Queue](https://spiral.dev/docs/queue-configuration)
- [GRPC](https://spiral.dev/docs/grpc-configuration)
- [Websockets](https://spiral.dev/docs/websockets-configuration)
- [Logger](https://spiral.dev/docs/basics-logging/#roadrunner-handler)
- [Metrics](https://spiral.dev/docs/advanced-prometheus-metrics)
- [Locks](https://spiral.dev/docs/advanced-locks)

> [!NOTE]
> Read more about RoadRunner configuration in the [RoadRunner documentation](https://docs.roadrunner.dev).

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
