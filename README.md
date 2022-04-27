# RoadRunner v2 bridge to Spiral Framework

-----

[![Latest Stable Version](https://poser.pugx.org/spiral/roadrunner-bridge/version)](https://packagist.org/packages/spiral/roadrunner-bridge)
[![Unit tests](https://github.com/spiral/roadrunner-bridge/actions/workflows/main.yml/badge.svg)](https://github.com/spiral/roadrunner-bridge/actions/workflows/main.yml)
[![Static analysis](https://github.com/spiral/roadrunner-bridge/actions/workflows/static.yml/badge.svg)](https://github.com/spiral/roadrunner-bridge/actions/workflows/static.yml)
[![StyleCI](https://github.styleci.io/repos/447581540/shield)](https://github.styleci.io/repos/447581540)

## Requirements

Make sure that your server is configured with following PHP version and extensions:

- PHP 7.4+
- Spiral framework 2.9+

## Installation

To install the package:

```bash
composer require spiral/roadrunner-bridge
```

After package install you need to add bootloaders from the package in your application on the top of the list.

```php
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
protected const LOAD = [
    RoadRunnerBridge\HttpBootloader::class,
    RoadRunnerBridge\QueueBootloader::class,
    RoadRunnerBridge\CacheBootloader::class,
    RoadRunnerBridge\GRPCBootloader::class,
    RoadRunnerBridge\CommandBootloader::class,
    
    // ...
];
```

## Usage

### Cache

#### Configuration

You can create config file `app/config/cache.php` if you want to configure Cache storages. In this file, you may specify
which cache driver you would like to be used by default throughout your application.

```php
<?php

declare(strict_types=1);

use Spiral\Cache\Storage\ArrayStorage;
use Spiral\Cache\Storage\FileStorage;

return [

    'default' => 'array',
    
    /**
     *  Aliases for storages, if you want to use domain specific storages
     */
    'aliases' => [
        'user-data' => 'localMemory'
    ],

    'storages' => [

        'local' => [
            // Alias for ArrayStorage type
            'type' => 'array',
        ],
        
        'localMemory' => [
            'type' => ArrayStorage::class,
        ],

        'file' => [
            // Alias for FileStorage type
            'type' => 'file',
            'path' => __DIR__.'/../../runtime/cache',
        ],
        
    ],

    /**
     * Aliases for storage types
     */
    'typeAliases' => [
        'array' => ArrayStorage::class,
        'file' => FileStorage::class,
    ],
];
```

#### Working with default storage

At first, you need to specify default storage in configuration file, and it will be bound
with `Psr\SimpleCache\CacheInterface` and will be automatically delivered by the container (auto-wiring).

```php
'default' => 'array',
```

```php
use Psr\SimpleCache\CacheInterface;

class MyService {

    private CacheInterface $cache;
    private PostReposiory $posts;
    
    public function __construct(CacheInterface $cache, PostReposiory $posts) 
    {
        $this->cache = $cache;
        $this->posts = $posts;
    }

    public function handle(): void
    {
        $posts = $this->posts->findAll();
        $this->cache->set('posts', $posts);
        
        // ...
    }
}
```

#### Working with storage provider

Storage provider provides convenient access to the underlying implementations of the `Psr\SimpleCache\CacheInterface`
cache contract. Using the Storage provider, you may access various cache stores via the `storage` method. The key passed
to the store method should correspond to one of the storages listed in the `storages` configuration array in your cache
configuration file:

```php
use Spiral\Cache\CacheStorageProviderInterface;

class MyService {

    private CacheStorageProviderInterface $cacheManager;
    private PostReposiory $posts;
    
    public function __construct(CacheStorageProviderInterface $cacheManager, PostReposiory $posts) 
    {
        $this->cacheManager = $cacheManager;
        $this->posts = $posts;
    }

    public function handle(): void
    {
        /** @var \Psr\SimpleCache\CacheInterface $cache */
        $cache = $this->cacheManager->storage('inMemory');
        
        $posts = $this->posts->findAll();
        $this->cache->set('posts', $posts);
        
        // ...
    }
}
```

#### Working with domain specific storages

You may even associate a domain specific storage name with one of the configured storage and then request storage by its
alias:

```php
'aliases' => [
    'user-data' => 'localMemory'
],
'storages' => [
    'localMemory' => [
        'type' => ArrayStorage::class,
    ],
    // ...
],
```

```php
use Spiral\Cache\CacheStorageProviderInterface;

class MyService {

    private CacheStorageProviderInterface $cacheManager;
    private PostReposiory $posts;
    
    public function __construct(CacheStorageProviderInterface $cacheManager, UserRepository $posts) 
    {
        $this->cacheManager = $cacheManager;
        $this->posts = $posts;
    }

    public function handle(): void
    {
        /** @var \Psr\SimpleCache\CacheInterface $cache */
        $cache = $this->cacheManager->storage('user-data');
        
        // ...
    }
}
```

#### Adding custom cache storages

There are two ways to add cache storages:

1. Create a new class that implements `\Psr\SimpleCache\CacheInterface` interface.

```php
final class DatabaseStorage implements \Psr\SimpleCache\CacheInterface
{
    private string $table;
    
    public function __construct(string $table) 
    {
        $this->table = $table;
    }

    // ..
}
```

And then just use it in your config

```php
'memcached' => [
    'type' => DatabaseStorage::class,
    'table' => 'cache',
],
```

Cache storage will be automatically resolved by `Spiral\Core\FactoryInterface` and all the data from its config will be
passed in its `__constructor`. It will look like:

```php
$factory->make(DatabaseStorage::class, [
    'table' => 'cache'
])
```

#### Console commands

| Command               | Description                            |
|-----------------------|----------------------------------------|
| cache:clear           | Clear cache for default cache storage  |
| cache:clear {storage} | Clear cache for specific cache storage |

### Queue

Roadrunner queues provide a unified queueing API across a variety of different queue backends. Full information about
supported pipelines you can read on official site https://roadrunner.dev/docs/beep-beep-jobs.

#### Configuration

You can create config file `app/config/queue.php` if you want to configure Queue connections:

```php
<?php

declare(strict_types=1);

use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\BeanstalkCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\SQSCreateInfo;

return [
    /**
     *  Default queue connection name
     */
    'default' => env('QUEUE_CONNECTION', 'sync'),

    /**
     *  Aliases for queue connections, if you want to use domain specific queues
     */
    'aliases' => [
        // 'mail-queue' => 'roadrunner',
        // 'rating-queue' => 'sync',
    ],
    
    /**
     * Queue connections
     * Drivers: "sync", "roadrunner"
     */
    'connections' => [
        'sync' => [
            // Job will be handled immediately without queueing
            'driver' => 'sync',
        ],
        'roadrunner' => [
            'driver' => 'roadrunner',
            'default' => 'local',
            'pipelines' => [
                'local' => [
                    'connector' => new MemoryCreateInfo('local'),
                    // Run consumer for this pipeline on startup (by default)
                    // You can pause consumer for this pipeline via console command
                    // php app.php queue:pause local
                    'consume' => true 
                ],
                // 'amqp' => [
                //     'connector' => new AMQPCreateInfo('bus', ...),
                //     // Don't consume jobs for this pipeline on start
                //     // You can run consumer for this pipeline via console command
                //     // php app.php queue:resume local
                //     'consume' => false 
                // ],
                // 
                // 'beanstalk' => [
                //     'connector' => new BeanstalkCreateInfo('bus', ...),
                // ],
                // 
                // 'sqs' => [
                //     'connector' => new SQSCreateInfo('amazon', ...),
                // ],
            ]
        ],
    ],
    
    'driverAliases' => [
        'sync' => \Spiral\Queue\Driver\SyncDriver::class,
        'roadrunner' => \Spiral\RoadRunnerBridge\Queue\Queue::class,
    ],
    
    'registry' => [
        'handlers' => [],
    ],
];
```

Connections with `roadrunner` driver will automatically declare pipelines without configuring on the RoadRunner side. If
pipeline will be declared via RoadRunner config, Queue manager will just connect to it (without declaring).

#### Job handler

To run a job, you must create a proper job handler. The handler must implement `Spiral\Queue\HandlerInterface`. Handlers
are responsible only for job execution. Use `Spiral\Queue\JobHandler` to simplify your abstraction and perform
dependency injection in your handler method invoke:

```php
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PingHandler extends \Spiral\Queue\JobHandler
{
    public function invoke(HttpClientInterface $client, string $url): void
    {
        $status = $client->request('GET', $url)->getStatusCode() === 200;
        echo $status ? 'PONG' : 'ERROR';
    }
}
```

```php
use Spiral\Queue\QueueInterface;
use Spiral\Queue\QueueConnectionProviderInterface;
use Psr\Container\ContainerInterface;

class MyService {

    private ContainerInterface $container;
    private  QueueInterface $queue;
    
    /**
     * @param QueueInterface $queue - Default queue will be injected
     * @param ContainerInterface $container
     */
    public function __construct(QueueInterface $queue, ContainerInterface $container) 
    {
        $this->container = $container;
        $this->queue = $queue;
    }

    public function handle()
    {
        $queue = $this->queue;
        
        // OR gets queue from manager 

        /** @var QueueManager $queueManager */
        $queueManager = $this->container->get(QueueConnectionProviderInterface::class);
        $queue = $queueManager->getConnection('sync');
        
        $queue->push(PingHandler::class, ['url' => 'https://google.com']);
    }
}
```

You can bind job names with class handlers:

```php
use Spiral\Queue\QueueRegistry;
/** @var QueueRegistry $registry */
$registry = $queue = $this->container->get(QueueRegistry::class);
$registry->setHandler('ping', PingHandler::class);

/** @var QueueInterface $queue */
$queue = $this->container->get(QueueInterface::class);
$queue->push('ping', ['url' => 'https://google.com']);
```

#### Job DTO's

Job DTO classes are very simple, normally containing only a `__invoke` or `handle` method that is invoked when the job
is processed by the queue. To get started, let's take a look at an example job class. In this example, we'll pretend we
ping a URL:

```php
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Ping
{
    private string $url;
    
    public function __construct(string $url) 
    {
        $this->url = $url;
    }
    
    public function __invoke(HttpClientInterface $client): void
    {
        $status = $client->request('GET', $this->url)->getStatusCode() === 200;
        echo $status ? 'PONG' : 'ERROR';
    }
}
```

```php
use Spiral\Queue\QueueInterface;

class MyService {

    private QueueInterface $queue;
    
    public function __construct(QueueInterface $queue) 
    {
        $this->queue = $queue;
    }

    public function handle()
    {
        $this->queue->pushObject(new Ping('https://google.com'));
    }
}
```

### Callable jobs

If you have simply job and you don't want to create job handler you may use php closures to handle some work.

```php
use Spiral\Queue\QueueInterface;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MyService {

    private QueueInterface $queue;
    
    public function __construct(QueueInterface $queue) 
    {
        $this->queue = $queue;
    }
    
    public function handle()
    {
        $url = 'https://google.com';
        
        $this->queue->pushCallable(static function(HttpClientInterface $client) use($url) : void {
            $status = $client->request('GET', $url)->getStatusCode() === 200;
            echo $status ? 'PONG' : 'ERROR';
        });
    }
}
```

### Domain specific queues

Domain specific queues are an important part of an application. You can create aliases for exists connections and use them
instead of real names. When you decide to switch queue connection for alias, you can do it in one place.

```php
'aliases' => [
    'user-data' => 'sync',
],
```

```php
use Spiral\Queue\QueueInterface;
use Spiral\Queue\QueueConnectionProviderInterface;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MyService {

    private QueueInterface $queue;
    
    public function __construct(QueueConnectionProviderInterface $manager) 
    {
        $this->queue = $manager->getConnection('user-data');
    }

    public function handle()
    {
        $this->queue->push(...);
    }
}
```

#### Push on specific queue

For some queue connections you can specify queue _name where the specific job should be pushed.

```php
use Spiral\Queue\Options;

$this->queue->push('mail.job', [...], Options::onQueue('amqp'));
```

In case of RoadRunner driver queue are the same as pipelines._

### Handle failed jobs

By default, all failed jobs will be sent into spiral log. But you can change default behavior. At first, you need to
create your own implementation for `Spiral\RoadRunnerBridge\Queue\Failed\FailedJobHandlerInterface`

##### Custom handler example

```php
use Spiral\RoadRunnerBridge\Queue\Failed\FailedJobHandlerInterface;
use Cycle\Database\DatabaseInterface;
use Spiral\Queue\SerializerInterface;

class DatabaseFailedJobsHandler implements FailedJobHandlerInterface
{
    private DatabaseInterface $database;
    private SerializerInterface $serializer;
    
    public function __construct(DatabaseInterface $database, SerializerInterface $serializer)
    {
        $this->database = $database;
        $this->serializer = $serializer;
    }

    public function handle(string $driver, string $queue, string $job, array $payload, \Throwable $e): void
    {
        $this->database
            ->insert('failed_jobs')
            ->values([
                'driver' => $driver,
                'queue' => $queue,
                'job_name' => $job,
                'payload' => $this->serializer->serialize($payload),
                'error' => $e->getMessage(),
            ])
            ->run();
    }
}
```

Then you need to bind your implementation with `Spiral\RoadRunnerBridge\Queue\Failed\FailedJobHandlerInterface`
interface.

```php
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\RoadRunnerBridge\Queue\Failed\FailedJobHandlerInterface;

final class QueueFailedJobsBootloader extends Bootloader
{
    protected const SINGLETONS = [
        FailedJobHandlerInterface::class => \App\Jobs\DatabaseFailedJobsHandler::class,
    ];
}
```

And register this bootloader after `Spiral\RoadRunnerBridge\Bootloader\QueueBootloader` in your application

```php
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
protected const LOAD = [
    RoadRunnerBridge\HttpBootloader::class,
    RoadRunnerBridge\QueueBootloader::class,
    App\Bootloader\QueueFailedJobsBootloader::class,
    RoadRunnerBridge\CommandBootloader::class,
    
    // ...
];
```

#### Console commands

| Command                  | Description                                         |
|--------------------------|-----------------------------------------------------|
| roadrunner:list          | List available roadrunner pipelines                 |
| roadrunner:pause {name}  | Pause consuming jobs for pipeline with given name   |
| roadrunner:resume {name} | Resume consuming jobs for pipeline with given name  |

> `name` - it's a RR jobs pipeline name

##### Example

```php
'memory' => [
    'driver' => 'roadrunner',
    'connector' => new MemoryCreateInfo('local'),
    'consume' => true // Consume jobs 
],
```

```bash
php app.php queue:pause local
```

### GRPC

The GRPC protocol provides an extremely efficient way of cross-service communication for distributed applications. The
public toolkit includes instruments to generate client and server code-bases for many languages allowing the developer
to use the most optimal language for the task.

## Configuration

Install `protoc-gen-php-grpc` from [pre-build binaries](https://github.com/spiral/roadrunner-binary/releases).

Create config file `app/config/grpc.php` if you want to configure generate service classes:

```php
<?php

declare(strict_types=1);

return [
    /**
     * Path to protoc-gen-php-grpc library.
     * Default: null 
     */
    'binaryPath' => null,
    // 'binaryPath' => __DIR__.'/../../protoc-gen-php-grpc',

    'services' => [
        __DIR__.'/../../proto/echo.proto',
    ],
];
```

Then run console command:

```bash
php app.php grpc:generate
```

#### Console commands

| Command                                    | Description                                             |
|--------------------------------------------|---------------------------------------------------------|
| grpc:services                              | List available GRPC services                            |
| grpc:generate {path=auto} {namespace=auto} | Generate GPRC service code using protobuf specification |

#### Example GRPC service

```
// app/proto/echo.proto

syntax = "proto3";
package service;

option php_namespace = "App\\GRPC\\EchoService";
option php_metadata_namespace = "App\\GRPC\\EchoService\\GPBMetadata";

service Echo {
    rpc Ping (Message) returns (Message) {
    }
}

message Message {
    string msg = 1;
}
```

Put proto file into `app/config/grpc.php`

```php
'services' => [
    __DIR__.'/../../proto/echo.proto',
],
```

Run console command:

```bash
php app.php grpc:generate
```

Implement `EchoInterface` interface

```php
// app/src/GRPC/EchoService/EchoService.php

namespace App\GRPC\EchoService;

use Spiral\RoadRunner\GRPC\ContextInterface;

class EchoService implements EchoInterface
{
    public function Ping(ContextInterface $ctx, Message $in): Message
    {
        $out = new Message();

        return $out->setMsg(date('Y-m-d H:i:s').': PONG');
    }
}
```

Configure `grpc` section in the RoadRunner yaml config:

```yaml
grpc:
  listen: "tcp://localhost:9001"
  proto:
    - "proto/echo.proto"
```

Start server

```bash
./rr serve
```

Full example Echo GRPC service you can find [here](https://github.com/spiral/roadrunner-grpc/tree/master/example/echo)

------

### Roadrunner config example

```yaml
rpc:
  listen: tcp://127.0.0.1:6001

server:
  command: "php app.php"
  relay: pipes

# serve static files
static:
  dir: "public"

http:
  address: 0.0.0.0:8080
  middleware: [ "gzip", "static" ]
  static:
    dir: "public"
    forbid: [ ".php", ".htaccess" ]
  pool:
    num_workers: 1
    supervisor:
      max_worker_memory: 100

jobs:
  consume: [ ]
  pool:
    num_workers: 2
    supervisor:
      max_worker_memory: 100

kv:
  local:
    driver: memory
    config:
      interval: 60
  redis:
    driver: redis
    config:
      addrs:
        - "localhost:6379"
#grpc:
#  listen: "tcp://localhost:9001"
#  proto:
#    - "first.proto"
```

Read more about RoadRunner configuration on official site https://roadrunner.dev.
