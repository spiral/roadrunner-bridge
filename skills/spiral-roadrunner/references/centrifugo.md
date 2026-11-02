# Centrifugo

`CentrifugoBootloader` connects the RoadRunner `centrifuge` plugin: Centrifugo proxies client events
(connect, subscribe, RPC …) to a `centrifuge` worker, where a Spiral service answers each one. It also
binds the server API client and registers the `centrifugo` broadcasting driver. Request and response
classes come from `roadrunner/centrifugo` (`RoadRunner\Centrifugo\...`).

## Config: `app/config/centrifugo.php`

```php
use RoadRunner\Centrifugo\Request\RequestType;

return [
    // Request type value => service (class name, Autowire or object).
    'services' => [
        RequestType::Connect->value => ConnectService::class,
        RequestType::Subscribe->value => SubscribeService::class,
        RequestType::RPC->value => RPCService::class,
    ],
    // '*' or a request type value => interceptor or list of interceptors.
    'interceptors' => [
        '*' => [TelemetryInterceptor::class],
        RequestType::Connect->value => AuthInterceptor::class,
    ],
];
```

Request types: `connect`, `refresh`, `subrefresh`, `subscribe`, `publish`, `rpc`. An unknown interceptor
key throws `Centrifugo\Exception\ConfigurationException`. Register a service for every event the
Centrifugo proxy config sends to RoadRunner: an event without one ends in the error handler.

At runtime the same lists are reachable through `Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface`
(`register(RequestType, $service)`) and `Centrifugo\Interceptor\RegistryInterface`
(`register($type, $interceptor)`), e.g. from a bootloader.

## Services

A service implements `Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface::handle(RequestInterface $request): void`
and answers through the request object: `respond(<Type>Response)`, `error($code, $message)`,
`disconnect($code, $reason)`.

```php
use RoadRunner\Centrifugo\Payload\ConnectResponse;
use RoadRunner\Centrifugo\Request;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

final class ConnectService implements ServiceInterface
{
    /** @param Request\Connect $request */
    public function handle(Request\RequestInterface $request): void
    {
        try {
            $request->respond(new ConnectResponse(user: $userId, channels: ['public']));
        } catch (\Throwable $e) {
            $request->error($e->getCode(), $e->getMessage());
        }
    }
}
```

Each request runs in the `centrifugo-request` scope with `RoadRunner\Centrifugo\Request\RequestInterface`
bound. Interceptors (`Spiral\Interceptors\InterceptorInterface`, legacy `CoreInterceptorInterface`) wrap
the call: the target is the service's `handle`, the argument is the request, and the
`RequestType` is in the context attributes. `*` interceptors run before type-specific ones.

An exception escaping a service goes to `Spiral\RoadRunnerBridge\Centrifugo\ErrorHandlerInterface`; the
default reports it through `ExceptionReporterInterface`. Rebind it to answer the client or log otherwise.

### Scaffolding

With `ScaffolderBootloader` loaded after `CentrifugoBootloader`:

```bash
php app.php create:centrifugo-handler Chat -t subscribe -a
```

`-t` is `connect` (default), `subscribe`, `rpc`, `refresh` or `publish`; `-a` injects
`CentrifugoApiInterface`; `-c` adds a class comment; `--namespace` overrides the default
`Endpoint\Centrifugo\Handler` (class postfix `Handler`). The class still has to be registered in
`centrifugo.services`.

## Server API and broadcasting

`RoadRunner\Centrifugo\CentrifugoApiInterface` (RPC through RoadRunner) publishes, broadcasts,
subscribes/unsubscribes and disconnects users, reads presence and channels. For `spiral/broadcasting`,
use the `centrifugo` driver alias:

```php
// app/config/broadcasting.php
'connections' => [
    'centrifugo' => ['driver' => 'centrifugo'],
],
```

`BroadcastInterface::publish($topics, $messages)` then broadcasts every message to all the topics.

## RoadRunner side

`.rr.yaml` needs a `centrifuge` section (proxy address the Centrifugo server calls, the Centrifugo gRPC API
address, a pool); the Centrifugo server's own config points its proxies at RoadRunner. Both are described
in the RoadRunner Centrifuge plugin docs.
