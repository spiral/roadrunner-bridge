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
- Regenerated files differ from the protoc output only by the `json_name` option buf records in field
  comments and the embedded descriptor; JSON names stay the same.

## Centrifugo `subrefresh`

`RequestType::SubRefresh` is now `subrefresh` (was `sub_refresh`). Rename the key in
`centrifugo.services` and `centrifugo.interceptors`; `sub_refresh` now throws `ConfigurationException`.

## Internal classes

Classes outside the `@api` surface moved into `*\Internal\*` namespaces: console commands
(`Console\Command\*`), `FallbackDispatcher`, `GRPC\Generator\GeneratorRegistry`,
`GRPC\ProtoRepository\FileRepository` / `CompositeRepository`. Search the application for
`Spiral\RoadRunnerBridge\` and keep only the bootloaders, configs, `RoadRunnerMode` and the public
interfaces: run commands by name, select the queue driver through the `roadrunner` alias, and implement
`ProtoFilesRepositoryInterface` / `GeneratorRegistryInterface` yourself instead of reusing the internal
implementations.
