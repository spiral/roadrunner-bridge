# RoadRunner v2 bridge to Spiral Framework

-----

## Requirements

Make sure that your server is configured with following PHP version and extensions:

- PHP 7.4+

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
    RoadRunnerBridge\CommandBootloader::class,
    
    // ...
];
```

## Configuration

### Roadrunner

#### Example

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
```

### Queue configuration

```php
<?php

declare(strict_types=1);

use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
use Spiral\SendIt\MailJob;
use Spiral\SendIt\MailQueue;

return [
    /**
     *  Default Queue Connection Name
     */
    'default' => env('QUEUE_CONNECTION', 'memory'),

    /**
     * Queue Connections
     * Drivers: "sync", "roadrunner"
     */
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'memory' => [
            'driver' => 'roadrunner',
            'connector' => new MemoryCreateInfo('local'),
            // Run consumer for this connection on startup (by default)
            // You can pause consumer for this connection via console command
            // php app.php queue:pause local
            'consume' => true 
        ],
        'amqp' => [
            'driver' => 'roadrunner',
            'connector' => new AMQPCreateInfo('local'),
            // Don't consume jobs for this connection on start
            // You can run consumer for this connection via console command
            // php app.php queue:resume local
            'consume' => false 
        ],
    ],
    
    'registry' => [
        'handlers' => [
            MailQueue::JOB_NAME => MailJob::class,
        ],
    ],
];
```

## Usage

### Queue

#### Job handler

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
use Spiral\RoadRunnerBridge\Queue\QueueManager;
use Psr\Container\ContainerInterface;

class MyService {

    private ContainerInterface $container;
    
    public function __construct(ContainerInterface $container) 
    {
        $this->container = $container;
    }

    public function handle()
    {
        // Default queue
        /** @var QueueInterface $queue */
        $queue = $this->container->get(QueueInterface::class);
        
        // OR
        
        /** @var QueueManager $queueManager */
        $queueManager = $this->container->get(QueueManager::class);
        $queue = $queueManager->getConnection('sync');
        
        $queue->push(PingHandler::class, ['url' => 'https://google.com']);
    }
}
```

You can bind job names with class handlers:

```php
use Spiral\RoadRunnerBridge\Queue\QueueRegistry;
/** @var QueueRegistry $registry */
$registry = $queue = $this->container->get(QueueRegistry::class);
$registry->setHandler('ping', PingHandler::class);

/** @var QueueInterface $queue */
$queue = $this->container->get(QueueInterface::class);
$queue->push('ping', ['url' => 'https://google.com']);
```

#### Job DTO's

```php
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Ping extends \Spiral\Queue\JobHandler
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
use Spiral\RoadRunnerBridge\Queue\QueueManager;
use Psr\Container\ContainerInterface;

class MyService {

    private ContainerInterface $container;
    
    public function __construct(ContainerInterface $container) 
    {
        $this->container = $container;
    }

    public function handle()
    {
        // Default queue
        /** @var QueueInterface $queue */
        $queue = $this->container->get(QueueInterface::class);
        
        // OR
        
        /** @var QueueManager $queueManager */
        $queueManager = $this->container->get(QueueManager::class);
        $queue = $queueManager->getConnection('sync');
        
        $queue->pushObject(new Ping('https://google.com'));
    }
}
```

### Callable jobs

```php
use Spiral\Queue\QueueInterface;
use Spiral\RoadRunnerBridge\Queue\QueueManager;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MyService {

    private ContainerInterface $container;
    
    public function __construct(ContainerInterface $container) 
    {
        $this->container = $container;
    }

    public function handle()
    {
        // Default queue
        /** @var QueueInterface $queue */
        $queue = $this->container->get(QueueInterface::class);
        
        // OR
        
        /** @var QueueManager $queueManager */
        $queueManager = $this->container->get(QueueManager::class);
        $queue = $queueManager->getConnection('sync');
        
        $url = 'https://google.com';
        
        $queue->pushCallable(static function(HttpClientInterface $client) use($url) : void {
            $status = $client->request('GET', $url)->getStatusCode() === 200;
            echo $status ? 'PONG' : 'ERROR';
        });
    }
}
```

#### Handle failed jobs

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

    public function handle(string $connection, string $queue, string $job, array $payload, \Throwable $e): void
    {
        $this->database
            ->insert('failed_jobs')
            ->values([
                'connection' => connection,
                'queue' => $queue,
                'job_name' => $job,
                'payload' => $this->serializer->serialize($payload),
                'error' => $e->getMessage(),
            ])
            ->run();
    }
}
```

Then you need to bind your implementation with interface.

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

| Command             | Description                                      |
|---------------------|--------------------------------------------------|
| queue:list          | List available queue connections                 |
| queue:pause {name}  | Pause consuming jobs for queue with given name   |
| queue:resume {name} | Resume consuming jobs for queue with given name  |

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
