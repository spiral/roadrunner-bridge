<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger\Formatter;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;

final class JsonStringFormatter extends NormalizerFormatter
{
    public function __construct(
        private readonly string $loggerPrefix = '',
        private readonly RoadRunnerLogsMode $loggerMode = RoadRunnerLogsMode::Production,
        ?string $dateFormat = null,
    ) {
        parent::__construct($dateFormat);
    }

    public function format(array|LogRecord $record): string
    {
        $normalized = $this->normalizeRecord($record);

        \assert(\is_string($record['level']));

        $level = match (Logger::toMonologLevel($record['level'])) {
            Level::Error, Level::Critical, Level::Alert, Level::Emergency => 'error',
            Level::Warning => 'warning',
            Level::Info, Level::Notice => 'info',
            Level::Debug => 'debug',
        };

        \assert($record['datetime'] instanceof \DateTimeInterface);

        $ts = $this->loggerMode === RoadRunnerLogsMode::Development
            ? $record['datetime']->format(\DateTimeInterface::RFC3339)
            : $record['datetime']->format('Uu000');

        \assert(\is_string($record['channel']));

        $data = [
            'level' => $this->loggerMode === RoadRunnerLogsMode::Development ? \strtoupper($level) : $level,
            'ts' => $ts,
            'logger' => $this->loggerPrefix . $record['channel'],
            'msg' => $record['message'],
        ]
            + ($normalized['context'] ?? [])
            + ($normalized['extra'] ?? []);

        return \json_encode($data, JSON_THROW_ON_ERROR);
    }
}
