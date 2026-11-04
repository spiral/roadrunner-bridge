# gRPC

`GRPCBootloader` serves gRPC services from a `grpc` worker, configures `grpc:generate` and wires
`spiral/grpc-client` for outgoing calls. It depends on `RoadRunnerBootloader` and
`GrpcClientBootloader`; service discovery needs the tokenizer listeners (`TokenizerListenerBootloader`).

## Config: `app/config/grpc.php`

```php
return [
    // Code generation — see "Generating code" below.
    'bufPath' => null,
    'protocPath' => null,
    'pluginPath' => null,
    'bufTemplate' => null,
    'servicesBasePath' => directory('root') . 'proto',
    'services' => [
        directory('root') . 'proto/echo/echo.proto',
    ],
    'generatedPath' => directory('root') . 'generated',
    'namespace' => 'App',
    'generators' => [],

    // Server interceptors, in order.
    'interceptors' => [],

    // spiral/grpc-client: an array or a GrpcClientConfig object.
    'client' => [
        'interceptors' => [],
        'services' => [],
    ],
];
```

`GRPCConfig` exposes each key through a getter. A bootloader can append to the lists instead:
`GRPCBootloader::addInterceptor()` and `addGenerator()` (class name, `Autowire` or object).

## Serving

1. Describe the service in a `.proto` file and run `php app.php grpc:generate`: it writes the message
   classes and a `<Name>Interface` extending `Spiral\RoadRunner\GRPC\ServiceInterface`.
2. Implement the interface in a class the tokenizer scans. Each method takes
   `(Spiral\RoadRunner\GRPC\ContextInterface $ctx, <Request> $in)` and returns the response message.
3. List the same `.proto` in `.rr.yaml` (`grpc.proto`) next to `grpc.listen` and a pool.

```php
final class PingService implements PingServiceInterface
{
    public function __construct(private readonly Repository $repo) {} // resolved from the container

    public function Ping(ContextInterface $ctx, PingRequest $in): PingResponse
    {
        $ctx->getValue(ResponseHeaders::class)?->set('x-pinged-by', $in->getFrom());
        return new PingResponse();
    }
}
```

**Discovery.** A tokenizer listener collects every instantiable class implementing a `ServiceInterface`
descendant (tokenizer scope `grpc-services`: narrow it with `tokenizer.scopes.grpc-services.directories`).
One implementation per service interface — a second one throws `LogicException`. `php app.php grpc:services`
lists service name, implementation and file. At worker start every service is fetched from the container;
one that fails to resolve is reported as `ServiceRegistrationException` and skipped.

**Errors.** Throw `Spiral\RoadRunner\GRPC\Exception\GRPCException` (or its `InvokeException`,
`NotFoundException`, `UnauthenticatedException` …) with a `StatusCode` to answer with a gRPC status.
Exceptions are reported through `ExceptionReporterInterface`.

**The current call.** Inside a call the container scope `grpc-request` holds
`Spiral\RoadRunnerBridge\GRPC\UnaryCallInterface`: `getContext()`, `getMethod()` (`Spiral\RoadRunner\GRPC\Method`),
`getMessage()` (the decoded request).

### Interceptors

Every call runs through the `interceptors` pipeline before the service method. An interceptor implements
`Spiral\Interceptors\InterceptorInterface` (the legacy `CoreInterceptorInterface` still works) and gets a
`CallContext` whose target is the service object and the method name, and whose arguments are
`[$ctx, $message]`. Return the response message, or throw a `GRPCException` to reject.

```php
final class AuthInterceptor implements InterceptorInterface
{
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        [$ctx] = $context->getArguments();
        $ctx->getValue('authorization') ?? throw new UnauthenticatedException('No token');
        return $handler->handle($context);
    }
}
```

## Generating code: `grpc:generate`

```bash
php app.php grpc:generate [path] [namespace]
```

The command compiles the `services` proto files with [buf](https://buf.build); with the bridge's own
template it runs locally, with no network access. Optional `path` (relative to the current directory) and `namespace` override
`generatedPath` / `namespace`; with neither set, both fall back to the kernel's directory and namespace.
Relative paths in the config resolve from the application root.

### Binaries

Three are needed: `buf`, `protoc` (buf runs its built-in PHP generator) and `protoc-gen-php-grpc`. Each
is looked up as: the config key (`bufPath`, `protocPath`, `pluginPath`) → `<root>/<name>[.exe]` → `PATH`.
A miss fails with a hint naming the config key and the DLoad command. Download them into the project root
with DLoad (`vendor/bin/dload get`) or `rr get-grpc-tools` — see [`binaries.md`](binaries.md). The protoc
major must match the installed `google/protobuf` (5.36.x → protoc 36.x).

### Inputs and imports

- `servicesBasePath` is the buf input: every import resolves from it, and every file in `services` must
  lie inside it. Write imports relative to it (`import "shop/common.proto";`).
- Without it, each service's own directory is a separate input.
- A service is generated together with the other proto files in its directory and subdirectories, plus
  the files they import from elsewhere in the input. `google/...` imports are skipped: those classes come
  from `google/protobuf` and `google/common-protos`.
- Generated classes are placed by namespace under `generatedPath`, with the base `namespace` cut from the
  path. Classes outside it (a `php_namespace` of another vendor) keep their full namespace as the path;
  the command prints the PSR-4 rules to add to `composer.json` for them.
- Failures make the command exit with 1 and skip the generators. A missing proto file, an empty entry
  and one outside `servicesBasePath` are reported and left out; the rest still generates. A buf error
  fails the whole input: with `servicesBasePath` set all services are one buf run, so one proto file
  that does not compile leaves every service ungenerated.

### Custom `buf.gen.yaml`

`bufTemplate` points to your own template, used as is — for managed mode, extra plugins or BSR
dependencies (with your own `buf.yaml`). The bridge then only runs buf:

- Its `out` directories decide where files go: `generatedPath` and `namespace` no longer relocate them;
  `generatedPath` only tells which files count as generated for the generators.
- Imports are not added: set `include_imports: true` on each plugin, or the generated code references
  classes of imported files that were never generated.
- `protocPath` and `pluginPath` are not used; the template names the binaries.
- buf runs in the application root, so relative paths in the template resolve from it
  (`protoc_path: protoc`, `local: protoc-gen-php-grpc`) — on Windows with the `.exe` suffix.

### Generators

A `Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface` runs after a successful generation with the
generated file paths, `generatedPath` and the namespace — e.g. to write a client config or a service map.
Register through `generators` or `GRPCBootloader::addGenerator()`; one instance per class.

Rebind `Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface` to take the proto list
from somewhere other than `services`.

## Client

`spiral/grpc-client` (needs `ext-grpc`) gives each generated service interface a client implementation in
the container. The bridge builds its `GrpcClientConfig` from the `client` key:

```php
use Spiral\Grpc\Client\Config\ConnectionConfig;
use Spiral\Grpc\Client\Config\ServiceConfig;
use Spiral\Grpc\Client\Interceptor\ExecuteServiceInterceptors;
use Spiral\Grpc\Client\Interceptor\RetryInterceptor;
use Spiral\Grpc\Client\Interceptor\SetTimeoutInterceptor;

'client' => [
    'interceptors' => [
        SetTimeoutInterceptor::createConfig(10_000),
        RetryInterceptor::createConfig(maximumAttempts: 3),
        ExecuteServiceInterceptors::class,
    ],
    'services' => [
        new ServiceConfig(
            connections: new ConnectionConfig('mail-service:9001'),
            interfaces: [\GRPC\Mail\MailSenderInterface::class],
        ),
    ],
],
```

Then inject `MailSenderInterface` and call it with a `new Spiral\RoadRunner\GRPC\Context([])`. TLS,
connection rotation and interceptor order: `vendor/spiral/grpc-client/README.md`.

## Tracing

Server and client calls are traced out of the box through the framework tracer (`spiral/telemetry`); spans
reach OpenTelemetry with `spiral/otel-bridge` ^2.0 — earlier versions drop the attributes of failed calls.

- Server: each call is a `SERVER` span named after the method (`ping.dto.PingService/Ping`), with the
  interceptors inside it. The trace continues from the incoming `traceparent` metadata, and the response
  headers carry the span context back.
- Client: `Spiral\RoadRunnerBridge\GRPC\Client\TracingInterceptor` already stands first in the common client
  `interceptors` — one `CLIENT` span per call, retries included, with `traceparent` in the call metadata.
  For a span per attempt, list it in the common list after `RetryInterceptor`; the bridge keeps that
  position. Its place is the common list only: added to a service's `interceptors` as well, it wraps each
  call twice.
- Any status other than `OK` marks the span as an error.
