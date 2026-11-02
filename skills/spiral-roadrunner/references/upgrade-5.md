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

- `QueueBootloader` loads `LockBootloader`: pipelines are declared under a RoadRunner lock. The `lock`
  plugin needs no `.rr.yaml` section, but a custom rr build must include it; `lockPrefix` in the queue config changes the lock names.
- A push that creates a missing `consume: true` pipeline resumes it; 4.x left it paused until
  `rr:jobs:consume`.
- A push may name a configured pipeline by its RoadRunner (connector) name: it gets the config defaults
  and creates the pipeline when it is missing; 4.x connected without the defaults and never created it.
- Per-push headers are added to the pipeline's default headers instead of replacing them, and an explicit
  per-push delay of `0` overrides a non-zero default.
- A Kafka pipeline whose `options` are not `KafkaOptions` sends them with the topic `default` instead of
  an empty topic.

## Centrifugo `subrefresh`

`RequestType::SubRefresh` is now `subrefresh` (was `sub_refresh`). Rename the key in
`centrifugo.services` and `centrifugo.interceptors`; `sub_refresh` now throws `ConfigurationException`.

## Internal classes

Classes outside the `@api` surface moved into `*\Internal\*` namespaces. Search the application for
`Spiral\RoadRunnerBridge\` and keep only the bootloaders, configs, `RoadRunnerMode` and the public
interfaces: run commands by name, select the queue driver through the `roadrunner` alias, and implement
`ProtoFilesRepositoryInterface` / `GeneratorRegistryInterface` yourself instead of reusing the internal
implementations.
