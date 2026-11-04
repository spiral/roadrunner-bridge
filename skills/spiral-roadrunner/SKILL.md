---
name: spiral-roadrunner
description: Run a Spiral Framework app on RoadRunner with spiral/roadrunner-bridge — bootloaders, `.rr.yaml` worker modes, and the HTTP, Jobs queue, gRPC (services, interceptors, `grpc:generate` with buf, client, tracing), Centrifugo, KV cache, Lock, Metrics and Logger integrations. Use when installing or configuring the bridge, writing a gRPC service, queue job or Centrifugo handler, generating gRPC code from proto files, downloading the rr, buf or protoc binaries, upgrading the bridge to 5.x, or migrating `.rr.yaml` to RoadRunner 3.
---

# Spiral on RoadRunner: spiral/roadrunner-bridge

The bridge runs a Spiral application as RoadRunner workers and wires each RoadRunner plugin into Spiral
components: HTTP → `spiral/http`, Jobs → `spiral/queue`, KV → `spiral/cache`, Centrifugo →
`spiral/broadcasting`, app-logger → Monolog, plus gRPC, Lock and Metrics. When a name here disagrees with
the installed `vendor/spiral/roadrunner-bridge`, the installed source wins.

Requirements: PHP 8.2+, Spiral 3.14+, RoadRunner 3 (`.rr.yaml` `version: '3'`), `ext-protobuf`
recommended for gRPC.

## Public API boundary

Only classes marked `@api` are public: the bootloaders in `Spiral\RoadRunnerBridge\Bootloader`, the
configs in `Spiral\RoadRunnerBridge\Config`, `RoadRunnerMode`, and the interfaces, exceptions and
attributes outside `*\Internal\*`. Everything under an `Internal` namespace (dispatchers, the queue
driver, console commands, the proto compiler) changes without notice. Reach internals through their
public seam instead: the queue driver through the `roadrunner` driver alias, dispatchers through the
bootloaders, commands through `CommandBootloader`, the compiler through `grpc:generate`.

## Setup

```bash
composer require spiral/roadrunner-bridge
```

List the bootloaders in the kernel. Order matters at two places: `CommandBootloader` registers only the
commands of plugins whose bootloader is already loaded, and `ScaffolderBootloader` adds
`create:centrifugo-handler` only after `CentrifugoBootloader` — keep both after the plugin bootloaders.

```php
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;

protected const LOAD = [
    // RoadRunnerBridge\RoadRunnerBootloader is a dependency of all of them; list it explicitly or not.
    RoadRunnerBridge\HttpBootloader::class,       // http plugin
    RoadRunnerBridge\QueueBootloader::class,      // jobs plugin
    RoadRunnerBridge\CacheBootloader::class,      // kv plugin
    RoadRunnerBridge\GRPCBootloader::class,       // grpc plugin (+ spiral/grpc-client)
    RoadRunnerBridge\CentrifugoBootloader::class, // centrifuge plugin
    RoadRunnerBridge\MetricsBootloader::class,    // metrics plugin
    RoadRunnerBridge\LoggerBootloader::class,     // app-logger plugin
    RoadRunnerBridge\LockBootloader::class,       // lock plugin
    RoadRunnerBridge\CommandBootloader::class,    // rr:jobs:*, grpc:* commands
    RoadRunnerBridge\ScaffolderBootloader::class, // create:centrifugo-handler
    // ...
];
```

Each plugin bootloader needs its Spiral counterpart loaded too — `HttpBootloader` pulls in the framework
`HttpBootloader` and needs PSR-17 factories (e.g. `spiral/nyholm-bridge`); queue, cache, broadcasting,
Monolog and tokenizer bootloaders come from the framework.

`RoadRunnerBootloader` binds the RoadRunner `EnvironmentInterface`, the Goridge `RPCInterface`
(address from `RR_RPC`) and `WorkerInterface`; every plugin client in the bridge talks to RoadRunner
through that RPC binding, so `.rr.yaml` needs an `rpc.listen` section.

## Worker modes

RoadRunner starts one `server.command` (usually `php app.php`) for every plugin pool and tells the worker
which plugin it serves through the `RR_MODE` env variable. `RoadRunnerMode` (injectable enum: `Http`,
`Jobs`, `Grpc`, `Centrifuge`, `Temporal`, `Unknown`) reads it, and the kernel picks the dispatcher whose
`canServe()` matches:

| Mode | Dispatcher registered by | Container scope | Per-request scope |
|---|---|---|---|
| `http` | `HttpBootloader` | `http` | `http-request` (set by `spiral/http`) |
| `jobs` | `QueueBootloader` | `queue` | `queue-task` (`TaskInterface`) |
| `grpc` | `GRPCBootloader` | `grpc` | `grpc-request` (`UnaryCallInterface`) |
| `centrifuge` | `CentrifugoBootloader` | `centrifugo` | `centrifugo-request` (`RequestInterface`) |

A mode without its bootloader fails at start with `DispatcherNotFoundException` naming the bootloader to
add to the kernel; `temporal` points to `spiral/temporal-bridge`. Outside RoadRunner the mode is `Unknown` and
the console/other dispatchers run as usual.

The HTTP worker hands every request to `Spiral\Http\Http`. An exception that escapes the HTTP pipeline is
reported through `ExceptionHandlerInterface` and answered with 500 — with the rendered error in the body
outside production. Each request, job, gRPC call and Centrifugo event ends with
`FinalizerInterface::finalize()`.

## Commands

| Command | Comes from | Details |
|---|---|---|
| `php app.php rr:jobs:list` / `rr:jobs:pause` / `rr:jobs:consume` | `CommandBootloader` + `QueueBootloader` | `references/queue.md` |
| `php app.php grpc:generate` / `grpc:services` | `CommandBootloader` + `GRPCBootloader` | `references/grpc.md` |
| `php app.php create:centrifugo-handler` | `ScaffolderBootloader` + `CentrifugoBootloader` | `references/centrifugo.md` |
| `vendor/bin/dload get` | `internal/dload` (suggested) | `references/binaries.md` |
| `vendor/bin/rr get` / `get-grpc-tools` / `make-config` / `versions` | `roadrunner/cli` 3 (suggested) | `references/binaries.md` |
| `rr serve` | the RoadRunner binary | RoadRunner docs |

## Reference

Read the file for the integration in hand:

- [`references/queue.md`](references/queue.md) — Jobs: the `roadrunner` queue driver, pipelines declared
  from PHP, payload serialization, retries, `rr:jobs:*` commands.
- [`references/grpc.md`](references/grpc.md) — gRPC server: services, discovery, interceptors, the `grpc`
  config; `grpc:generate` with buf (binaries, imports, custom `buf.gen.yaml`, generators); the gRPC client;
  server and client tracing.
- [`references/centrifugo.md`](references/centrifugo.md) — Centrifugo: per-request-type services,
  interceptors, error handler, the broadcasting driver, `create:centrifugo-handler`.
- [`references/services.md`](references/services.md) — KV cache storage, Lock, Metrics, Logger (Monolog
  handler, env variables).
- [`references/binaries.md`](references/binaries.md) — getting `rr`, `buf`, `protoc`,
  `protoc-gen-php-grpc`: DLoad, `roadrunner/cli`, Docker, other sources, CI tokens.
- [`references/upgrade-5.md`](references/upgrade-5.md) — moving an app from bridge 4.x to 5.x: TCP removal,
  protoc → buf, Centrifugo `subrefresh`, `roadrunner/*` package names.
- [`references/rr3-config.md`](references/rr3-config.md) — migrating `.rr.yaml` and RoadRunner-dependent
  PHP code to RoadRunner 3 (official v3 guide): audit, then edits the user approves.

RoadRunner's own `.rr.yaml` options live in the RoadRunner docs (docs.roadrunner.dev); the Spiral
component behind each plugin (queue, cache, broadcasting, HTTP) keeps its own config and docs at
spiral.dev/docs.
