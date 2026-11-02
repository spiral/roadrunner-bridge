# Migrating the RoadRunner config to v3

Brings `.rr.yaml` and the PHP code that talks to RoadRunner in line with RoadRunner 3. Source: the official
guide https://docs.roadrunner.dev/docs/general/v3-migration and the plugin pages it links; when it
disagrees with this file, the guide wins.

The application owner may have migrated part of the config by hand already, and some changes cannot be
told apart from an already migrated config by reading the file. So this is an **audit first, edit with
consent** procedure: nothing is changed before the user approves it.

## 1. Collect the configs

Every RoadRunner config the project runs with: `.rr.yaml` and its variants (`.rr.*.yaml`, `rr.yaml`),
files pulled in through `include`, overrides passed as `rr serve -o key=value` in Dockerfiles, compose
files, CI and scripts, and the `envfile` they name.

## 2. Audit

Walk the checklist below for every config and classify each item: **done** (already in v3 shape),
**fix** (mechanical change, show the exact edit), **decide** (needs the user's knowledge), **n/a**. The
audit is complete when every item has a class for every config.

| Area | Signal in the config / code | v3 target | Class when found |
|---|---|---|---|
| Version | root `version` | `version: "3"` | fix |
| TCP plugin | a `tcp:` section | not supported — the app stays on RoadRunner 2025.x (bridge 4.x); `tcp://` addresses for RPC and relays are unaffected | decide |
| HTTP middleware | `http.middleware` with 2+ entries | runs **left to right** now; v2025 ran right to left, so a list written for v2025 must be reversed to keep the execution order | always **decide**: the file cannot show whether it was reversed already — show the list and the order it will run in |
| HTTP/2 cleartext | `http.http2.h2c: true` | prior knowledge only, `Upgrade: h2c` is gone | decide (clients must speak prior knowledge) |
| AMQP connection | `amqp.addr` / `amqp.tls` at the top | named connections: `amqp.<name>.addr` (+ `tls`); no default connection | fix |
| AMQP pipelines | `driver: amqp` pipelines | `config.connection: <name>` on each; exchange settings under `config.exchange` (`name`, `type`, `durable`, `auto_delete`, `declare`), queue settings under `config.queue` (`name`, `routing_key`, `durable`, `auto_delete`, `exclusive`, `consumer_id`, `delete_on_stop`, `multiple_ack`, `requeue_on_fail`, `headers`, `declare`); `priority`, `prefetch`, `redial_timeout` stay in `config`; `config.version` removed | fix; **decide** where a flat key has no obvious nested twin, or a non-`fanout` exchange has no `routing_key` (now required) |
| AMQP from PHP | `AMQPCreateInfo` in `queue.pipelines` or `JobsInterface::create()` | `AMQPCreateInfo` has no connection argument: pass `queueHeaders: ['rr_connection' => '<name>']` | fix (ask which connection when there are several) |
| Logs | `logs.file_logger_options`, `max_size`, `max_age`, `max_backups`, `compress`, `encoding`, `err_output` | `logs.output: [stderr \| stdout \| <file>]` (per channel: `logs.channels.<name>.output`); rotation moves to an external tool; `encoding`/`err_output` are ignored — `mode: production` gives JSON | fix; **decide** on rotation |
| Log consumers | parsers/dashboards of RoadRunner JSON logs | field `time` (RFC 3339) instead of numeric `ts`, uppercase levels; records are dropped when the 1024-record queue is full | decide (outside the config) |
| Tracing | Zipkin exporter in `otel` | OTLP only; `otel` is a root section | fix exporter shape; **decide** the OTLP endpoint |
| Jobs pools | both `jobs.pool` and `jobs.pools` | one of them | decide |
| Jobs `pool` header | PHP code setting a `pool` job header | reserved for named-pool routing — rename the header | decide |
| `pipeline_size` | `jobs.pipeline_size` unset | default is now `100000`; set `1000000` to keep the old limit | decide |
| NATS | `driver: nats` pipelines | stopping a pipeline keeps the stream unless `delete_stream_on_stop` is set; a restarted consumer may get retained messages again | decide |
| Centrifuge | PHP calls to the `centrifuge.RateLimit` RPC method | removed — delete the calls | fix |
| Envfile | root `envfile` | loads without experimental mode; the file must exist | fix (drop the key or ship the file) |
| `server.on_init.env` | present | now overrides inherited env values; arguments with spaces need the command as a list | decide |
| Goridge codecs | PHP code using MessagePack for RPC | gone: JSON, protobuf, Gob, raw | fix |
| Metrics | Grafana/Prometheus dashboards on jobs metrics | counter types changed, requeues counted separately | decide (outside the config) |
| Plugins removed | `websockets`, `broadcast`, `reload` sections | not shipped; Centrifugo replaces websockets/broadcast, a file watcher replaces `reload` | decide |

## 3. Report and ask

Show the user the table for their project: each found item with its class, the current value and the
proposed v3 value. Ask once for all **fix** items together, and ask each **decide** item separately with the
options it has. Changes the user declines stay out.

## 4. Apply

Edit only the approved items. Keep comments, key order and env placeholders (`${VAR}`) as they were.
Show the resulting diff.

## 5. Verify

With the user's go-ahead, start RoadRunner 3 with the migrated config (`rr serve -c <config>`) and check
that every plugin starts without a config error and the workers boot. For an AMQP or NATS change, push
one job through each changed pipeline.
