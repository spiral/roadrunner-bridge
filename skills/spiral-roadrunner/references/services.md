# KV cache, Lock, Metrics, Logger

The smaller plugins: each bootloader binds a RoadRunner client over the shared RPC connection, so they
work from any worker and from console commands while RoadRunner runs. The client interfaces belong to the
`roadrunner/*` packages — read the method contracts in `vendor/roadrunner/<package>/src`.

## KV → spiral/cache (`CacheBootloader`)

Registers the `roadrunner` cache storage type. A storage names the KV storage from `.rr.yaml`:

```php
// app/config/cache.php
return [
    'default' => 'roadrunner',
    'storages' => [
        'roadrunner' => [
            'type' => 'roadrunner',
            'driver' => 'local', // the `kv.local` section of .rr.yaml
        ],
    ],
];
```

```yaml
# .rr.yaml
kv:
  local:
    driver: memory
    config: {}
```

Values are serialized by `Spiral\RoadRunner\KeyValue\Serializer\SerializerInterface`, bound to
`DefaultSerializer` (PHP `serialize`). Rebind it to `IgbinarySerializer` or `SodiumSerializer` (encrypts
with a key) in a bootloader. `Spiral\RoadRunner\KeyValue\FactoryInterface::select($name)` gives a raw
`StorageInterface` for a KV storage outside the cache config.

## Lock (`LockBootloader`)

Binds `RoadRunner\Lock\LockInterface` — distributed locks held by the RoadRunner `lock` plugin, shared by
all workers.

```php
$id = $lock->lock('import:catalog', ttl: 60, waitTTL: 5); // lock id, or false when not acquired
if ($id !== false) {
    try { /* ... */ } finally { $lock->release('import:catalog', $id); }
}
```

Also `lockRead()` (shared lock), `exists()`, `updateTTL()`, `forceRelease()`.

## Metrics (`MetricsBootloader`)

Binds `Spiral\RoadRunner\Metrics\MetricsInterface` for the RoadRunner `metrics` plugin (Prometheus
endpoint at `metrics.address`). Collectors are declared in `.rr.yaml` under `metrics.collect`, or at
runtime with `declare($name, Collector::counter()->withHelp(...)->withLabels(...))`. Then `add()`, `sub()`,
`set()`, `observe()` by name, with label values in declared order.

## Logger (`LoggerBootloader`)

Adds a Monolog handler under the channel `roadrunner` (needs `MonologBootloader` from
`spiral/monolog-bridge`). Inside a RoadRunner worker the handler sends records to the `app-logger` plugin,
so they appear in RoadRunner's own log output; outside RoadRunner (`RoadRunnerMode::Unknown`) it falls back
to PHP's `error_log`.

Route the application log through it by making `roadrunner` the default channel — `MONOLOG_DEFAULT_CHANNEL=roadrunner`
(e.g. in `server.env` of `.rr.yaml`) — or by listing it in the monolog config. Environment variables:

| Variable | Effect |
|---|---|
| `MONOLOG_DEFAULT_LEVEL` | Minimum level of the handler, default `INFO` |
| `RR_LOGGER_PREFIX` | Prefix added to the channel name in the `channel` attribute |

RoadRunner renders each record itself, by `logs.mode` and the `app` channel of `logs.channels`: JSON in
`production`, `key=value` in `development`, with its own `time` and `DEBUG`/`INFO`/`WARN`/`ERROR` levels
(notice → `INFO`; critical, alert, emergency → `ERROR`). `logger` is always `app`; the Monolog channel goes
to the `channel` attribute, then come the context and extra fields, normalized by Monolog. An attribute is a
string: nested maps become dot-separated keys (`user.id`, `exception.previous.class`), lists a JSON string
(`exception.trace`, cut to 5 frames). Context keys named `time`, `level`, `msg`, `logger` or `channel` are
sent as `context.<key>`. `time` is when RoadRunner received the record, so a buffering Monolog handler in
front of this one stamps a whole flush with one time.
`Spiral\RoadRunnerBridge\Logger\Handler` is public — construct it yourself to attach it to another channel
or with other settings.
