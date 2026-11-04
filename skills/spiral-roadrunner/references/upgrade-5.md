# Upgrading an application from bridge 4.x to 5.x

Bridge 5.x runs on RoadRunner 3. Work through every section that applies; each names what to search the
application for.

## RoadRunner 3 and packages

- Download a RoadRunner 3 binary (`rr get` from `roadrunner/cli` 3, or DLoad), then migrate `.rr.yaml`
  and the PHP code that depends on RoadRunner behaviour (HTTP middleware order, AMQP named connections,
  logger output …) with [`rr3-config.md`](rr3-config.md) — an audit the user approves before any edit.
- With DLoad, set `version="^3"` for `rr` in `dload.xml`; `rr get` from `roadrunner/cli` 3 takes 3.x by
  default.
- PHP packages moved to the `roadrunner/*` vendor, the metapackage included (`spiral/roadrunner` →
  `roadrunner/roadrunner`, `spiral/roadrunner-http` → `roadrunner/http`, `spiral/roadrunner-jobs` →
  `roadrunner/jobs`, `spiral/goridge` → `roadrunner/goridge`, `roadrunner-php/centrifugo` →
  `roadrunner/centrifugo` …). Replace the old names in `composer.json` with the new ones; PHP namespaces
  (`Spiral\RoadRunner\…`, `Spiral\Goridge\…`, `RoadRunner\…`) are unchanged.

## TCP removed

RoadRunner 3 ships no TCP plugin, so the bridge dropped it without a deprecation period: `TcpBootloader`,
`TcpConfig`, `app/config/tcp.php`, everything under `Spiral\RoadRunnerBridge\Tcp\`, the TCP service
scaffolder command and `RoadRunnerMode::Tcp`. Search for `Tcp` and remove the bootloader, config and
services. An application that needs the TCP plugin stays on bridge 4.x with RoadRunner 2025.x. `tcp://`
addresses for RPC and relays are not the plugin and keep working.

## gRPC code generation: protoc → buf

`grpc:generate` now runs buf; see "Generating code" in [`grpc.md`](grpc.md).

- `binaryPath` is removed; use `pluginPath`. New optional keys: `bufPath`, `protocPath`, `bufTemplate`.
- Imports resolve from `servicesBasePath` only, no longer from the directory of the importing file:
  rewrite `import "b.proto"` in `foo/a.proto` as `import "foo/b.proto"`. Every file in `services` must lie
  inside `servicesBasePath`.
- Download `buf`, `protoc` and `protoc-gen-php-grpc` (DLoad or `rr get-grpc-tools`);
  `rr download-protoc-binary` is no longer used.
- `grpc:generate` exits with 1 when a proto file is missing or does not compile; 4.x exited with 0 for a
  missing file. Check scripts and CI steps that run it.
- The base `namespace` is cut from generated paths only as a whole segment: with `App`, a
  `php_namespace` of `AppExtra\...` used to land in `Extra/...` and now lands in `AppExtra/...`.
  Regenerate and review the diff of the generated directory.
- Regenerated files differ from the protoc output in the proto file path — the `# source:` header and
  the descriptor's file name are relative to `servicesBasePath` now — and in the `json_name` buf records
  in field comments and the descriptor; class names, code and JSON field names stay the same. Regenerate
  all proto files at once rather than mixing old and new output.

## Queue pipelines declared from PHP

- `QueueBootloader` loads `LockBootloader` and declares pipelines under a RoadRunner lock: a custom rr
  build must include the `lock` plugin.
- A push that creates a missing `consume: true` pipeline resumes it; 4.x left it paused until
  `rr:jobs:consume`.
- A push may name a configured pipeline by its RoadRunner (connector) name: it gets the config defaults
  and creates the pipeline when it is missing; 4.x connected without the defaults and never created it.
- Per-push headers are added to the pipeline's default headers instead of replacing them, and an explicit
  per-push delay of `0` overrides a non-zero default.
- A Kafka pipeline whose `options` are not `KafkaOptions` throws `InvalidArgumentException` on push; 4.x
  sent such pushes with an empty topic.
- A Kafka pipeline without `options` whose connector consumes a single topic pushes to that topic; 4.x
  pushed to the topic `default`.

## Centrifugo `subrefresh`

`RequestType::SubRefresh` is now `subrefresh` (was `sub_refresh`). Rename the key in
`centrifugo.services` and `centrifugo.interceptors`; `sub_refresh` now throws `ConfigurationException`.

## Logger output

The `roadrunner` handler sends records through the app-logger level methods, and RoadRunner formats them;
see "Logger" in [`services.md`](services.md).

- Keeping the 4.x lines: set `RR_LOGGER_MODE=production` (or `development`; `prod` and `dev` work too)
  explicitly, in `.env` or `server.env`. It selects `RawHandler`, which writes the same JSON line as 4.x,
  `logger` with the channel included, and bypasses `logs.level` as 4.x did. Unset and any other value now
  mean `native`, the RoadRunner-rendered format below; 4.x treated them as `production`. A consumer that
  reads `logger` or `ts` without the variable set breaks.
- `RoadRunnerLogsMode` got the `Native` case, the value an injected enum has when the variable is unset; a
  `match` over the enum without `default` needs an arm for it.
- `new Handler(...)` no longer takes `loggerMode`, and `Handler` has no formatter. Code that constructs the
  4.x handler with `loggerMode` switches to `new RawHandler(...)`, which keeps that signature.
- In the native mode RoadRunner filters application records by `logs.level` (or
  `logs.channels.app.level`); 4.x lines bypassed it. With `level: warn` in `.rr.yaml`, info and notice records disappear: set the RoadRunner
  level as low as `MONOLOG_DEFAULT_LEVEL`.
- Log consumers (dashboards, alerts, parsers) of the native format:
  - `development` mode gives `key=value` lines, not JSON;
  - `time` (RFC 3339, RoadRunner's timezone, the moment RoadRunner received the record) replaces `ts`
    (Unix nanoseconds in `production`, RFC 3339 in `development`); the level is upper-case (`WARN`, not `warning`); the channel is in `channel`,
    `logger` is always `app`;
  - every value is a string (`"42"`, `"true"`): numeric filters need a cast;
  - nested context arrives as `key.subkey` fields; an exception as `exception.class`, `.message`, `.code`,
    `.file` and `.line` (no longer `file:line` in one field), a string `exception.trace` (no longer a list
    of 5 frames) and `exception.previous.*`; other objects as `<key>.@class` plus their public properties;
  - context keys named `time`, `level`, `msg`, `logger` or `channel` arrive as `context.<key>`.

## Internal classes

Classes outside the `@api` surface moved into `*\Internal\*` namespaces. Search the application for
`Spiral\RoadRunnerBridge\` and keep only the bootloaders, configs, `RoadRunnerMode` and the public
interfaces: run commands by name, select the queue driver through the `roadrunner` alias, and implement
`ProtoFilesRepositoryInterface` / `GeneratorRegistryInterface` yourself instead of reusing the internal
implementations.
