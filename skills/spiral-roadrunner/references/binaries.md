# Getting the binaries: rr, buf, protoc, protoc-gen-php-grpc

A Spiral app on RoadRunner needs the `rr` server binary; `grpc:generate` also needs `buf`, `protoc` and
`protoc-gen-php-grpc`. Pick the way that matches where the binary runs: DLoad or `roadrunner/cli` for a
project checkout and CI, a Docker stage for images.

Bridge 5.x needs RoadRunner 3: in `dload.xml` give `rr` the version `^3`; `rr get` from `roadrunner/cli` 3
downloads RoadRunner 3 by default. Bridge 5.x needs RoadRunner 3; check with `rr --version`.

Downloaded binaries stay out of git: add `/rr`, `/buf`, `/protoc`, `/protoc-gen-php-grpc` (and their
`.exe` on Windows) to `.gitignore`.

## DLoad (`internal/dload`)

Project-pinned downloads, declared in `dload.xml` next to `composer.json`:

```bash
composer require --dev internal/dload
```

```xml
<?xml version="1.0"?>
<dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="vendor/internal/dload/dload.xsd">
    <actions>
        <download software="rr" version="^3"/>
        <download software="buf" version="^1.50"/>
        <download software="protoc" version="^36.0"/>
        <download software="protoc-gen-php-grpc" version="^3.0"/>
    </actions>
</dload>
```

`vendor/bin/dload get` puts each binary into the project root — exactly where `grpc:generate` looks first.
`vendor/bin/dload get rr` fetches one. To refresh them on every install, add
`"post-update-cmd": ["dload get --no-interaction"]` to the composer scripts. Match the protoc major to the
installed `google/protobuf` (its minor: 5.36.x → protoc 36.x).

For the details — other targets, asset selection, the version registry cache, GitHub tokens — use the
`dload-fetch-tool` skill that ships with `internal/dload`, or `vendor/bin/dload --help`.

## roadrunner/cli (`vendor/bin/rr`)

```bash
composer require --dev roadrunner/cli
```

Version 3.x targets RoadRunner 3 (`spiral/roadrunner-cli` 2.x is the 2025.x line). It downloads through
DLoad but ignores the project's `dload.xml`.

| Command | Does |
|---|---|
| `rr get-binary` (`rr get`) | Downloads `rr` for this OS/arch into the current directory and writes an example `.rr.yaml`. `-f` version constraint, `-p <plugin>` (repeatable) or `--preset web` shape the config, `--no-config` skips it |
| `rr make-config` | Writes only the `.rr.yaml` example; same `-p` / `--preset` |
| `rr get-grpc-tools` | Downloads `buf`, `protoc`, `protoc-gen-php-grpc`. The protoc major follows the project's protobuf runtime (`ext-protobuf`, else the installed `google/protobuf`); `--buf`, `--protoc`, `--plugin` take constraints. Keeps a `buf`/`protoc` already there when its version fits; always re-downloads the plugin. Installs `protoc` without `include/` — buf supplies the well-known types |
| `rr versions` | Lists available RoadRunner versions (`-f` filters) |
| `rr download-protoc-binary` | Deprecated: only the plugin. Use `get-grpc-tools` |

Common options: `-l/--location` target directory, `-s/--stability`, `-o/--os`, `-a/--arch`. On a musl host
(Alpine) the musl build is picked automatically; `--os=unknown-musl` forces it.

## Docker

Copy the binary from the official image into the application image:

```dockerfile
FROM ghcr.io/roadrunner-server/roadrunner:3.0.0 AS roadrunner
FROM php:8.4-cli

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
```

Pin the tag to the RoadRunner version the project targets (tags: github.com/roadrunner-server/roadrunner
packages). Generate gRPC code at development time and commit the generated PHP, or run DLoad /
`rr get-grpc-tools` in a build stage — the code generation tools are not needed at runtime.

## Other sources

- Release assets and `.deb` packages: github.com/roadrunner-server/roadrunner/releases.
- `download-latest.sh` from the RoadRunner repository, Homebrew (`brew install roadrunner`), Chocolatey
  (`choco install roadrunner`) — check that the installed version is 3.x.
- A RoadRunner build with extra or third-party plugins is assembled with Velox (see the RoadRunner docs on
  building a custom binary).

## CI and rate limits

DLoad and `roadrunner/cli` read releases from the GitHub API. Pass a token to avoid the anonymous rate
limit: `GITHUB_TOKEN` (or `DLOAD_TOKEN_GITHUB_COM`); a GitHub Enterprise server takes
`DLOAD_TOKEN_<HOST>` plus `RR_GITHUB_API_URL` ending in `/api/v3`. The DLoad version registry cache lives in
the user cache dir; `DLOAD_CACHE_DIR` moves it, `DLOAD_CACHE_TTL=0` disables it.
