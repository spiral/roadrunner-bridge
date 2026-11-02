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
use RoadRunner\PsrLogger\Context\DefaultProcessor;
use RoadRunner\PsrLogger\RpcLogger;

/**
 * Sends Monolog records to the RoadRunner app-logger plugin.
 *
 * RoadRunner formats the line itself, according to its `logs` config. The Monolog channel,
 * with the optional prefix, goes to the `channel` attribute: the RoadRunner logger name is always `app`.
 *
 * @api
 */
final class Handler extends AbstractHandler implements ProcessableHandlerInterface
{
    use ProcessableHandlerTrait;

    private readonly RpcLogger $rpcLogger;

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

        $processor = DefaultProcessor::createDefault();
        $this->rpcLogger = new RpcLogger(
            $logger,
            static fn(mixed $context): array => self::flatten((array) $processor($context)),
        );
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
            ['channel' => $this->loggerPrefix . $record->channel] + $record->context + $record->extra,
        );

        return $this->bubble === false;
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
                ? \json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)
                : $value;
        }

        return $result;
    }
}
