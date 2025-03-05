<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger as RoadRunnerLogger;

final class Handler extends AbstractProcessingHandler
{
    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function __construct(
        private readonly RoadRunnerLogger $logger,
        private readonly string $loggerPrefix = '',
        private readonly RoadRunnerLogsMode $loggerMode = RoadRunnerLogsMode::Production,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct(Logger::toMonologLevel($level), $bubble);
    }

    protected function write(array|LogRecord $record): void
    {
        if (\is_array($record) && empty($record)) {
            throw new \InvalidArgumentException('LogRecord should not be empty if is array');
        }
        \assert($record['datetime'] instanceof \DateTimeInterface);

        $level = match (Logger::toMonologLevel($record['level'])) {
            Level::Error, Level::Critical, Level::Alert, Level::Emergency => 'error',
            Level::Warning => 'warning',
            Level::Info, Level::Notice => 'info',
            Level::Debug => 'debug',
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
