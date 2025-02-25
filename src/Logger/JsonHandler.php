<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger as RoadRunnerLogger;

/**
 * Custom log sender to Roadrunner in JSON encoding.
 */
final class JsonHandler extends AbstractProcessingHandler
{
    public const FORMAT = "%message% %context% %extra%\n";

    public function __construct(
        private readonly RoadRunnerLogger $logger,
        string|FormatterInterface $formatter = self::FORMAT,
        private readonly string $loggerPrefix = '',
        private readonly RoadRunnerLogsMode $loggerMode = RoadRunnerLogsMode::Production,
    ) {
        parent::__construct();

        if (\is_string($formatter)) {
            $formatter = new LineFormatter($formatter);
        }

        $this->setFormatter($formatter);
    }

    protected function write(array|LogRecord $record): void
    {
        if (\is_array($record) && empty($record)) {
            throw new \InvalidArgumentException('LogRecord should not be empty if is array');
        }
        \assert($record['datetime'] instanceof \DateTimeInterface);

        $level = $record['level'] instanceof Level ? $record['level']->value : $record['level'];
        $level =  match ($level) {
            Level::Error->value, Level::Critical->value => 'error',
            Level::Warning->value, Level::Alert->value, Level::Emergency->value => 'warning',
            Level::Info->value, Level::Notice->value => 'info',
            Level::Debug->value => 'debug',
            default => throw new \LogicException('Unknown level: ' . $level),
        };

        $ts = $this->loggerMode === RoadRunnerLogsMode::Development
            ? $record['datetime']->format(\DateTimeInterface::RFC3339)
            : $record['datetime']->format('Uu000');

        $data = [
            'level' => $this->loggerMode === RoadRunnerLogsMode::Development ? \strtoupper($level) : $level,
            'ts' => $ts,
            'logger' => $this->loggerPrefix . $record['channel'],
            'msg' => $record['message'],
        ]
        + ($record['context'] ?? [])
        + ($record['extra'] ?? []);

        try {
            $this->logger->log(\json_encode($data, JSON_THROW_ON_ERROR) . PHP_EOL);
        } catch (\JsonException $e) {
            $this->logger->error($e->getMessage() . PHP_EOL);
        }
    }
}
