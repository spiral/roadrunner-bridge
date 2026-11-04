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

* add the spiral-roadrunner agent skill ([#148](https://github.com/spiral/roadrunner-bridge/issues/148)) ([f3c427d](https://github.com/spiral/roadrunner-bridge/commit/f3c427de2f2ddd1c6aa2ac67844ad2fcb0464ec7))
* **centrifugo:** name the sub-refresh request type `subrefresh` ([#125](https://github.com/spiral/roadrunner-bridge/issues/125)) ([b9ae762](https://github.com/spiral/roadrunner-bridge/commit/b9ae7620edb60e7e9e2e1f5b2dcd1236b0bf5589))
* depend on the `roadrunner/*` packages ([#125](https://github.com/spiral/roadrunner-bridge/issues/125)) ([b9ae762](https://github.com/spiral/roadrunner-bridge/commit/b9ae7620edb60e7e9e2e1f5b2dcd1236b0bf5589))
* **grpc:** generate gRPC code with buf ([#147](https://github.com/spiral/roadrunner-bridge/issues/147)) ([ba3627b](https://github.com/spiral/roadrunner-bridge/commit/ba3627b4a5af0f80640e8b0b72ddd5f3b9751561))
* **grpc:** trace incoming and outgoing gRPC calls ([#157](https://github.com/spiral/roadrunner-bridge/issues/157)) ([32e8a34](https://github.com/spiral/roadrunner-bridge/commit/32e8a3425bbc0510e44b85e8d261d6a7447fdec8))
* **Logger:** let RoadRunner render application logs natively ([#150](https://github.com/spiral/roadrunner-bridge/issues/150)) ([52ab096](https://github.com/spiral/roadrunner-bridge/commit/52ab096fb6c0052d3b618e4e66a6b66707769136))
* **queue:** add `JobOptions` to set the job priority and auto-ack together with the pipeline ([#159](https://github.com/spiral/roadrunner-bridge/issues/159)) ([dda5a9e](https://github.com/spiral/roadrunner-bridge/commit/dda5a9e8abdae8f345ce7f929e661525f50cd5ae))
* **queue:** declare configured pipelines under a RoadRunner lock, with the `lockPrefix` option ([#153](https://github.com/spiral/roadrunner-bridge/issues/153)) ([5fb9f41](https://github.com/spiral/roadrunner-bridge/commit/5fb9f4107ae6e9e496f5747a7194a7afbb46e578))
* remove the TCP plugin support ([#125](https://github.com/spiral/roadrunner-bridge/issues/125)) ([b9ae762](https://github.com/spiral/roadrunner-bridge/commit/b9ae7620edb60e7e9e2e1f5b2dcd1236b0bf5589))
* support RoadRunner 3 ([#125](https://github.com/spiral/roadrunner-bridge/issues/125)) ([b9ae762](https://github.com/spiral/roadrunner-bridge/commit/b9ae7620edb60e7e9e2e1f5b2dcd1236b0bf5589))


### Bug Fixes

* **grpc:** accept a custom `LocatorInterface` that is not a tokenizer listener, and warn when a worker finds no services ([#158](https://github.com/spiral/roadrunner-bridge/issues/158)) ([ef26cf0](https://github.com/spiral/roadrunner-bridge/commit/ef26cf02a5fd856515caa0976d81c8e52e63e0c1))
* **grpc:** cut the namespace prefix from generated paths by whole segments only ([#147](https://github.com/spiral/roadrunner-bridge/issues/147)) ([ba3627b](https://github.com/spiral/roadrunner-bridge/commit/ba3627b4a5af0f80640e8b0b72ddd5f3b9751561))
* name the bootloader to add when no dispatcher serves the RoadRunner mode ([#151](https://github.com/spiral/roadrunner-bridge/issues/151)) ([1bd6be3](https://github.com/spiral/roadrunner-bridge/commit/1bd6be3d2340c5fea00e94fdf804abcbfd17d03b))
* **queue:** keep configured pipelines usable after another process declares them ([#153](https://github.com/spiral/roadrunner-bridge/issues/153)) ([5fb9f41](https://github.com/spiral/roadrunner-bridge/commit/5fb9f4107ae6e9e496f5747a7194a7afbb46e578))
* **queue:** push to the topic the Kafka connector consumes, and reject non-Kafka default options for Kafka pipelines ([#155](https://github.com/spiral/roadrunner-bridge/issues/155)) ([40e36b3](https://github.com/spiral/roadrunner-bridge/commit/40e36b3a64847f570a7141034d4cffb95ebbf116))


### Documentation

* describe the spiral-roadrunner agent skill in the README ([#161](https://github.com/spiral/roadrunner-bridge/issues/161)) ([8fbf0a8](https://github.com/spiral/roadrunner-bridge/commit/8fbf0a824ebea17e6649b3963ef45f973d69c6aa))
* restructure the README with current requirements ([#134](https://github.com/spiral/roadrunner-bridge/issues/134)) ([d0fc3af](https://github.com/spiral/roadrunner-bridge/commit/d0fc3af5650fdc7fc17490c4e481ee4c09d9a333))


### Code Refactoring

* move internal classes into `Internal` namespaces and enforce the public API surface with StructArmed ([#146](https://github.com/spiral/roadrunner-bridge/issues/146)) ([f630c51](https://github.com/spiral/roadrunner-bridge/commit/f630c51047a3bd700f421c73dfa625a6743c19cb))

## [4.1.0](https://github.com/spiral/roadrunner-bridge/compare/v4.0.0...v4.1.0) (2026-10-16)


### Features

* require PHP 8.2 ([08e28a0](https://github.com/spiral/roadrunner-bridge/commit/08e28a03d32bb2065c6542423c6ea6e4868cc211))


### Styles

* Fix Code Style CI and re-run it ([#127](https://github.com/spiral/roadrunner-bridge/issues/127)) ([dc8a6fe](https://github.com/spiral/roadrunner-bridge/commit/dc8a6fe1a2bfb90c633efc855fb957f0ec3bcfd9))


### Continuous Integration

* add release-please for automated releases ([002a536](https://github.com/spiral/roadrunner-bridge/commit/002a536b7b39c2f01ec37434bb214b7f4f7818a4))
* Use StructArmed on QA ([#128](https://github.com/spiral/roadrunner-bridge/issues/128)) ([ba3397d](https://github.com/spiral/roadrunner-bridge/commit/ba3397d7e720b3af5264df7e4bbdfbfb48175918))
