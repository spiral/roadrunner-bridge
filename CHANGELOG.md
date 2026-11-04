# Changelog

## [5.0.0](https://github.com/spiral/roadrunner-bridge/compare/v4.1.0...v5.0.0) (2026-11-04)


### ⚠ BREAKING CHANGES

* Requires RoadRunner 3; RoadRunner 2025.x and older are no longer supported. Upgrading from 4.x: follow the [migration guide](https://github.com/spiral/roadrunner-bridge/blob/v5.0.0/skills/spiral-roadrunner/references/upgrade-5.md).
* The `spiral/roadrunner-*` dependencies are replaced with `roadrunner/*` (`spiral/roadrunner` → `roadrunner/roadrunner`, `spiral/roadrunner-jobs` → `roadrunner/jobs`, `spiral/goridge` → `roadrunner/goridge`, `roadrunner-php/centrifugo` → `roadrunner/centrifugo`, …); PHP namespaces are unchanged.
* RoadRunner 3 has no TCP plugin: `TcpBootloader`, `TcpConfig`, `Spiral\RoadRunnerBridge\Tcp\*`, `RoadRunnerMode::Tcp` and the TCP service scaffolder are removed. `tcp://` addresses for RPC and relays keep working.
* **grpc:** `grpc:generate` compiles proto files with buf. `binaryPath` is removed in favour of `pluginPath` (new optional `bufPath`, `protocPath`, `bufTemplate`); imports resolve only from `servicesBasePath`; the command exits with 1 on a missing or broken proto file. Download buf, protoc and protoc-gen-php-grpc, e.g. with `rr get-grpc-tools` from `roadrunner/cli` 3.1.
* **centrifugo:** The `sub_refresh` request type is renamed to `subrefresh`; the old key in `centrifugo.services` and `centrifugo.interceptors` throws `ConfigurationException`.
* **queue:** `QueueBootloader` loads `LockBootloader`, so a custom RoadRunner build must include the `lock` plugin.
* **Logger:** Records go through the app-logger level methods and RoadRunner renders them by `logs.mode`: the channel is in `channel` (`logger` is always `app`), `time` replaces `ts`, levels are upper-case, values are strings, nested context arrives as dot-separated keys, and records are filtered by `logs.level`, so set it as low as `MONOLOG_DEFAULT_LEVEL`. `Logger\Handler` no longer accepts `loggerMode`; `RoadRunnerJsonFormatter` and `RoadRunnerLogsMode` are removed ([#162](https://github.com/spiral/roadrunner-bridge/issues/162)) and `RR_LOGGER_MODE` has no effect.
* Classes outside the `@api` surface moved into `*\Internal\*` namespaces; `Queue\PipelineRegistryInterface` is now `@api`.

### Features

* add the spiral-roadrunner agent skill ([#148](https://github.com/spiral/roadrunner-bridge/issues/148)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* **centrifugo:** name the sub-refresh request type `subrefresh` ([#125](https://github.com/spiral/roadrunner-bridge/issues/125)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* depend on the `roadrunner/*` packages ([#125](https://github.com/spiral/roadrunner-bridge/issues/125)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* **grpc:** generate gRPC code with buf ([#147](https://github.com/spiral/roadrunner-bridge/issues/147)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* **grpc:** trace incoming and outgoing gRPC calls ([#157](https://github.com/spiral/roadrunner-bridge/issues/157)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* **Logger:** let RoadRunner render application logs natively ([#150](https://github.com/spiral/roadrunner-bridge/issues/150)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* **queue:** add `JobOptions` to set the job priority and auto-ack together with the pipeline ([#159](https://github.com/spiral/roadrunner-bridge/issues/159)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* **queue:** declare configured pipelines under a RoadRunner lock, with the `lockPrefix` option ([#153](https://github.com/spiral/roadrunner-bridge/issues/153)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* remove the TCP plugin support ([#125](https://github.com/spiral/roadrunner-bridge/issues/125)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* support RoadRunner 3 ([#125](https://github.com/spiral/roadrunner-bridge/issues/125)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))


### Bug Fixes

* **grpc:** accept a custom `LocatorInterface` that is not a tokenizer listener, and warn when a worker finds no services ([#158](https://github.com/spiral/roadrunner-bridge/issues/158)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* **grpc:** cut the namespace prefix from generated paths by whole segments only ([#147](https://github.com/spiral/roadrunner-bridge/issues/147)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* name the bootloader to add when no dispatcher serves the RoadRunner mode ([#151](https://github.com/spiral/roadrunner-bridge/issues/151)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* **queue:** keep configured pipelines usable after another process declares them ([#153](https://github.com/spiral/roadrunner-bridge/issues/153)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* **queue:** push to the topic the Kafka connector consumes, and reject non-Kafka default options for Kafka pipelines ([#155](https://github.com/spiral/roadrunner-bridge/issues/155)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))


### Documentation

* describe the spiral-roadrunner agent skill in the README ([#161](https://github.com/spiral/roadrunner-bridge/issues/161)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))
* restructure the README with current requirements ([#134](https://github.com/spiral/roadrunner-bridge/issues/134)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))


### Code Refactoring

* move internal classes into `Internal` namespaces and enforce the public API surface with StructArmed ([#146](https://github.com/spiral/roadrunner-bridge/issues/146)) ([d6088dd](https://github.com/spiral/roadrunner-bridge/commit/d6088dd64fe47d663f3974d27112184533951f31))

## [4.1.0](https://github.com/spiral/roadrunner-bridge/compare/v4.0.0...v4.1.0) (2026-10-16)


### Features

* require PHP 8.2 ([08e28a0](https://github.com/spiral/roadrunner-bridge/commit/08e28a03d32bb2065c6542423c6ea6e4868cc211))


### Styles

* Fix Code Style CI and re-run it ([#127](https://github.com/spiral/roadrunner-bridge/issues/127)) ([dc8a6fe](https://github.com/spiral/roadrunner-bridge/commit/dc8a6fe1a2bfb90c633efc855fb957f0ec3bcfd9))


### Continuous Integration

* add release-please for automated releases ([002a536](https://github.com/spiral/roadrunner-bridge/commit/002a536b7b39c2f01ec37434bb214b7f4f7818a4))
* Use StructArmed on QA ([#128](https://github.com/spiral/roadrunner-bridge/issues/128)) ([ba3397d](https://github.com/spiral/roadrunner-bridge/commit/ba3397d7e720b3af5264df7e4bbdfbfb48175918))
