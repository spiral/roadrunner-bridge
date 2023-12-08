# RoadRunner v2 bridge to Spiral Framework

[![PHP Version Require](https://poser.pugx.org/spiral/roadrunner-bridge/require/php)](https://packagist.org/packages/spiral/roadrunner-bridge)
[![Latest Stable Version](https://poser.pugx.org/spiral/roadrunner-bridge/v/stable)](https://packagist.org/packages/spiral/roadrunner-bridge)
[![phpunit](https://github.com/spiral/roadrunner-bridge/actions/workflows/phpunit.yml/badge.svg)](https://github.com/spiral/roadrunner-bridge/actions)
[![psalm](https://github.com/spiral/roadrunner-bridge/actions/workflows/psalm.yml/badge.svg)](https://github.com/spiral/roadrunner-bridge/actions)
[![Codecov](https://codecov.io/gh/spiral/roadrunner-bridge/branch/master/graph/badge.svg)](https://codecov.io/gh/spiral/roadrunner-bridge/)
[![Total Downloads](https://poser.pugx.org/spiral/roadrunner-bridge/downloads)](https://packagist.org/packages/spiral/roadrunner-bridge)
[![StyleCI](https://github.styleci.io/repos/447581540/shield)](https://github.styleci.io/repos/447581540)
<a href="https://discord.gg/8bZsjYhVVk"><img src="https://img.shields.io/badge/discord-chat-magenta.svg"></a>

## Requirements

Make sure that your server is configured with following PHP version and extensions:

- PHP 8.1+
- Spiral Framework 3.7+

## Installation

To install the package:

```bash
composer require spiral/roadrunner-bridge
```

After package install you need to add bootloaders from the package in your application on the top of the list.

```php
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;

protected const LOAD = [
    RoadRunnerBridge\HttpBootloader::class, // Optional, if it needs to work with http plugin
    RoadRunnerBridge\QueueBootloader::class, // Optional, if it needs to work with jobs plugin
    RoadRunnerBridge\CacheBootloader::class, // Optional, if it needs to work with KV plugin
    RoadRunnerBridge\GRPCBootloader::class, // Optional, if it needs to work with GRPC plugin
    RoadRunnerBridge\CentrifugoBootloader::class, // Optional, if it needs to work with centrifugo server
    RoadRunnerBridge\TcpBootloader::class, // Optional, if it needs to work with TCP plugin
    RoadRunnerBridge\MetricsBootloader::class, // Optional, if it needs to work with metrics plugin
    RoadRunnerBridge\LoggerBootloader::class, // Optional, if it needs to work with app-logger plugin
    RoadRunnerBridge\ScaffolderBootloader::class, // Optional, to generate Centrifugo handlers and TCP services via Scaffolder
    RoadRunnerBridge\CommandBootloader::class,
    // ...
];
```

## Usage

- [Cache](https://spiral.dev/docs/basics-cache)
- [Queue](https://spiral.dev/docs/queue-configuration)
- [GRPC](https://spiral.dev/docs/grpc-configuration)
- [Websockets](https://spiral.dev/docs/websockets-configuration)
- [Logger](https://spiral.dev/docs/basics-logging/#roadrunner-handler)
- [Metrics](https://spiral.dev/docs/advanced-prometheus-metrics)
- [TCP](#tcp)
    - [Configuration](#configuration-2)
    - [Services](#services)

### TCP

RoadRunner includes TCP server and can be used to replace classic TCP setup with much greater performance and
flexibility.

#### Bootloader

Add `Spiral\RoadRunnerBridge\Bootloader\TcpBootloader` to application bootloaders list:

```php
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;

protected const LOAD = [
    // ...
    RoadRunnerBridge\TcpBootloader::class,
    // ...
];
```

This bootloader adds a dispatcher and necessary services for TCP to work.
Also, using the `addService` and `addInterceptors` methods can dynamically add services to TCP servers and configure
interceptors.

#### Configuration

Configure `tcp` section in the RoadRunner `.rr.yaml` configuration file with needed TCP servers. Example:

```yaml
tcp:
  servers:
    smtp:
      addr: tcp://127.0.0.1:22
      delimiter: "\r\n" # by default
    monolog:
      addr: tcp://127.0.0.1:9913

  pool:
    num_workers: 2
    max_jobs: 0
    allocate_timeout: 60s
    destroy_timeout: 60s
```

Create configuration file `app/config/tcp.php`. In the configuration, it's required to specify the services that
will handle requests from a specific TCP server. Optionally, interceptors can be added for each specific server.
With the help there, can add some logic before handling the request in service. Configuration example:

```php
<?php

declare(strict_types=1);

return [
    /**
     * Services for each server.
     */
    'services' => [
        'smtp' => SomeService::class,
        'monolog' => OtherService::class
    ],

    /**
     * Interceptors, this section is optional.
     * @see https://spiral.dev/docs/cookbook-domain-core/2.8/en#core-interceptors
     */
    'interceptors' => [
        // several interceptors
        'smtp' => [
            SomeInterceptor::class,
            OtherInterceptor::class
        ],
        'monolog' => SomeInterceptor::class // one interceptor
    ],

    'debug' => env('TCP_DEBUG', false)
];
```

#### Services

A service must implement the interface `Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface` with one required
method `handle`.
After processing a request, the `handle` method must return the `Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface`
object
with result (`RespondMessage`, `CloseConnection`, `ContinueRead`).

Example:

```php
<?php

declare(strict_types=1);

namespace App\Tcp\Service;

use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunnerBridge\Tcp\Response\RespondMessage;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

class TestService implements ServiceInterface
{
    public function handle(Request $request): ResponseInterface
    {
        // some logic

        return new RespondMessage('some message', true);
    }
}
```

The service can be generated using the **Scaffolder** component. Make sure that bootloader
`Spiral\RoadRunnerBridge\Bootloader\ScaffolderBootloader` is added in your application and run:

```bash
php app.php create:tcp-service Test
```

This will generate service **TestService** in the folder **Endpoint/Tcp/Service/TestService.php**.

> **Note**
> Namespace (and generation path) can be configured.
> Read more about [Scaffolder component](https://spiral.dev/docs/basics-scaffolding).

----

> **Note**
> Read more about RoadRunner configuration on official site https://roadrunner.dev.

## License:

MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information. Maintained by [Spiral Scout](https://spiralscout.com).
