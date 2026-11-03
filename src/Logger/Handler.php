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
use RoadRunner\PsrLogger\Context\FlattenProcessor;
use RoadRunner\PsrLogger\RpcLogger;

/**
 * Sends Monolog records to the RoadRunner app-logger plugin.
 *
 * RoadRunner formats the line itself, according to its `logs` config, and stamps it with the time it receives
 * the record. The Monolog channel, with the optional prefix, goes to the `channel` attribute: the RoadRunner
 * logger name is always `app`. Context and extra become attributes, nested maps as dot-separated keys;
 * a key named `channel`, `time`, `level`, `msg` or `logger` is sent as `context.<key>`.
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

        $this->rpcLogger = new RpcLogger($logger, new FlattenProcessor());
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

        $context = $record->context + $record->extra;
        // RpcLogger moves the RoadRunner fields aside, but `channel` is this handler's own attribute
        if (\array_key_exists('channel', $context)) {
            $context['context.channel'] = $context['channel'];
            unset($context['channel']);
        }

        $this->rpcLogger->log(
            $record->level->toPsrLogLevel(),
            $record->message,
            ['channel' => $this->loggerPrefix . $record->channel] + $context,
        );

        return $this->bubble === false;
    }

    #[\Override]
    public function reset(): void
    {
        parent::reset();
        $this->resetProcessors();
    }
}
