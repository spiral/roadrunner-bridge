# Jobs queue

`QueueBootloader` turns the RoadRunner Jobs plugin into a `spiral/queue` driver: jobs pushed through
`Spiral\Queue\QueueInterface` go to a RoadRunner pipeline, and a `jobs` worker consumes them and runs the
Spiral job handlers. Job handlers, `JobHandler`, serializers and queue interceptors are `spiral/queue`
itself — this file covers what the bridge adds.

## Configuration

Everything lives in `app/config/queue.php`. The connection uses the `roadrunner` driver alias the bridge
registers; `pipelines` is a **top-level** key of the queue config, not part of a connection.

```php
use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\Queue\Options;

return [
    'default' => env('QUEUE_CONNECTION', 'roadrunner'),
    'connections' => [
        'roadrunner' => [
            'driver' => 'roadrunner',
            'default' => 'memory',   // pipeline used when a push names none
            // 'pipeline' => 'mail', // preferred over 'default' when both are set
        ],
    ],
    // Pipelines the application declares in RoadRunner itself.
    'pipelines' => [
        'memory' => [
            'connector' => new MemoryCreateInfo('local'), // required: any CreateInfoInterface
            'consume' => true,                            // declare and resume when a jobs worker boots
        ],
        'mail' => [
            'connector' => new AMQPCreateInfo('mail', /* ... */),
            'consume' => false,                           // start later with rr:jobs:consume
            'options' => Options::delayed(5),             // defaults for pushes to this pipeline
        ],
    ],
    // 'lockPrefix' => 'roadrunner-bridge:jobs:',         // prefix of the declaration locks (the default)
];
```

The pipeline a push goes to: `Options::onQueue()` / `withQueue()` → the connection's `pipeline` → its
`default`. None of them set fails with `InvalidArgumentException`.

### Two ways a pipeline exists

- **Declared in `.rr.yaml`** (`jobs.pipelines`, consumed through `jobs.consume`) — push to it by name;
  leave it out of the PHP `pipelines` list. The bridge just connects to it.
- **Declared from PHP** — listed in `pipelines` with a `connector`. Whoever finds it missing creates it:
  a `jobs` worker at boot for `consume: true` pipelines (a failure is logged as a warning, not thrown), or
  the first push. The `.rr.yaml` then needs only the `jobs` section with a pool and the driver's own
  connection settings (e.g. `amqp`).

A pipeline declared from PHP is created under the RoadRunner lock `<lockPrefix>declare:<pipeline>` (the
`lock` plugin needs no `.rr.yaml` section, but a custom rr build must include it). One process declares
the pipeline while the others wait and then connect; the lock lives 20 seconds, a booting jobs worker
waits for it up to 25 seconds (and so takes over from a declarer that died), a push up to 10 seconds
before it fails. RoadRunner creates the pipeline paused: a `consume: true` one is resumed by its
declarer, any other stays paused until `rr:jobs:consume`.

A pipeline that already exists is left as is, so one paused with `rr:jobs:pause` stays paused across
worker restarts. A declarer that dies or fails between the declaration and the resume leaves the marker
`<lockPrefix>pending:<pipeline>`, and the next booting jobs worker resumes the pipeline;
`rr:jobs:pause` removes the marker. Set `lockPrefix` when several applications with the same pipeline
names share one RoadRunner.
To have pipelines ready before the first worker boots, declare them in `.rr.yaml` instead. Do not
declare them from a `server.on_init` script: RoadRunner 3 fails to start the `jobs` plugin with a
pipeline declared over RPC before it (`config not found by provided key: jobs.pipelines.<name>.config`).

The connector's name is the pipeline's name in RoadRunner; a push may name the pipeline by its
`pipelines` key or by that name, and a `pipelines` key wins over a connector name. Give each pipeline a
connector name of its own.

For a Kafka pipeline give `options` a `KafkaOptions` with the topic (`new KafkaOptions('orders')`): the
bridge does not read topics from the connector. Other `options` throw `InvalidArgumentException` on push;
without `options` pushes go to the topic `default`.

### Default options

`options` sets defaults for pushes to the pipeline. Per-push options override them:

- a delay from Spiral `Options` wins whenever it is set, `0` included;
- per-push headers are added to the default headers; a header set in both takes the per-push value;
- RoadRunner options override a default delay or priority only with a non-zero value, and auto-ack only
  with `true`.

## Pushing

```php
use Spiral\Queue\Options;
use Spiral\Queue\QueueInterface;

$id = $queue->push(SendMail::class, ['to' => $email], Options::onQueue('mail')->withDelay(30));
```

`$options` is either a Spiral `OptionsInterface` (queue, delay and headers are carried over, merged with
the pipeline defaults as above) or a
RoadRunner `Spiral\RoadRunner\Jobs\OptionsInterface` for driver features such as priority or
auto-ack — those go to the default pipeline, since they carry no queue name.

### Payload serialization

The payload is serialized with the serializer `spiral/queue` resolves for the job name (queue config
`registry.serializers`, default from `SerializerBootloader`). On the consuming side the bridge decides the
type to unserialize into:

1. an object payload travels with a `payload_class` header → unserialized into that class;
2. otherwise the type of the `$payload` parameter of the handler's `invoke()` → that class, or the raw
   string for `string`;
3. otherwise the serializer's default (an array for JSON).

Replace the logic by rebinding `Spiral\RoadRunnerBridge\Queue\PayloadDeserializerInterface`.

## Consuming

A `jobs` worker runs each task in the `queue-task` scope, where `Spiral\Queue\TaskInterface` holds the id,
queue, name, payload and headers, and calls the `spiral/queue` handler with driver `roadrunner`.

- success → the task is **acked**;
- `Spiral\Queue\Exception\RetryException` → reported and **requeued**; its `OptionsInterface` sets the
  retry delay and extra headers (`new RetryException('reason', Options::delayed(10))`);
- any other exception → reported and **nacked**; what happens next is the pipeline's RoadRunner retry
  settings.

## Lower-level access

- `Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface::getPipeline($name)` — the RoadRunner
  `Spiral\RoadRunner\Jobs\QueueInterface` for a pipeline, created from config when missing: pause, resume,
  dispatch prepared tasks, read stats.
- `Spiral\RoadRunner\Jobs\JobsInterface` and `ConsumerInterface` are bound as singletons.

## Commands

Registered by `CommandBootloader` when `QueueBootloader` is loaded:

| Command | Does |
|---|---|
| `rr:jobs:list` | Pipelines in RoadRunner with driver, priority, active/delayed/reserved jobs, readiness |
| `rr:jobs:pause <pipeline>` | Stops consuming the pipeline; takes the RoadRunner pipeline name (the connector name) |
| `rr:jobs:consume <pipeline>` | Resumes a paused pipeline; fails when it is not paused. Takes the `queue.pipelines` key, or the RoadRunner name for a pipeline missing from that list |

The two commands read the name differently, so give a `queue.pipelines` key the same name as its
connector — then one name works for both.
