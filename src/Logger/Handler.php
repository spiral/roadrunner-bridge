<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Handler\ProcessableHandlerTrait;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger as RoadRunnerLogger;
use RoadRunner\PsrLogger\RpcLogger;
use Spiral\RoadRunnerBridge\Logger\Internal\ContextNormalizer;

/**
 * Sends Monolog records to the RoadRunner app-logger plugin.
 *
 * RoadRunner formats the line itself, according to its `logs` config, and stamps it with the time it receives
 * the record. The Monolog channel, with the optional prefix, goes to the `channel` attribute: the RoadRunner
 * logger name is always `app`. Context and extra keys that clash with RoadRunner's own fields
 * (`time`, `level`, `msg`, `logger`) or with `channel` are sent as `context.<key>`.
 *
 * @api
 */
final class Handler extends AbstractHandler implements ProcessableHandlerInterface
{
    use ProcessableHandlerTrait;

    private const RESERVED_KEYS = ['time', 'level', 'msg', 'logger', 'channel'];

    private readonly RpcLogger $rpcLogger;
    private readonly ContextNormalizer $normalizer;

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function __construct(
        RoadRunnerLogger $logger,
        private readonly string $loggerPrefix = '',
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct(Logger::toMonologLevel($level), $bubble);

        $this->normalizer = new ContextNormalizer();
        // The context is normalized here already; RpcLogger's default processor would redo it with weaker rules.
        $this->rpcLogger = new RpcLogger($logger, static fn(mixed $context): mixed => $context);
    }

    #[\Override]
    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        if ($this->processors !== []) {
            $record = $this->processRecord($record);
        }

        $this->rpcLogger->log(
            $record->level->toPsrLogLevel(),
            $record->message,
            $this->attributes($record),
        );

        return $this->bubble === false;
    }

    #[\Override]
    public function reset(): void
    {
        parent::reset();
        $this->resetProcessors();
    }

    /**
     * Turns nested maps into dot-separated keys: `['exception' => ['class' => …]]` becomes `exception.class`.
     *
     * An app-logger attribute is a string pair, so a nested value would reach RoadRunner as one JSON string.
     * Lists stay as they are: `tags.0`, `tags.1` would read worse than a JSON array.
     *
     * @param array<array-key, mixed> $context
     * @return array<string, mixed>
     */
    private static function flatten(array $context, string $prefix = ''): array
    {
        $result = [];
        /** @var mixed $value */
        foreach ($context as $key => $value) {
            $key = $prefix . $key;
            if (\is_array($value) && $value !== [] && !\array_is_list($value)) {
                $result += self::flatten($value, $key . '.');
                continue;
            }

            // app-logger would encode a list without these flags
            $result[$key] = \is_array($value)
                ? \json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR)
                : $value;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(LogRecord $record): array
    {
        $normalized = $this->normalizer->normalizeValue($record->context + $record->extra);

        $attributes = ['channel' => $this->loggerPrefix . $record->channel];
        /** @var mixed $value */
        foreach (self::flatten(\is_array($normalized) ? $normalized : []) as $key => $value) {
            $attributes[\in_array($key, self::RESERVED_KEYS, true) ? 'context.' . $key : $key] = $value;
        }

        return $attributes;
    }
}
