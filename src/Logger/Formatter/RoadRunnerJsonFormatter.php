<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger\Formatter;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;

final class RoadRunnerJsonFormatter extends NormalizerFormatter
{
    public function __construct(
        private readonly string $loggerPrefix = '',
        private readonly RoadRunnerLogsMode $loggerMode = RoadRunnerLogsMode::Production,
        private readonly int $traceCount = 5,
        ?string $dateFormat = null,
    ) {
        parent::__construct($dateFormat);
    }

    /**
     * @param LogRecord|array{
     *     message: string,
     *     level: Logger::DEBUG|Logger::INFO|Logger::NOTICE|Logger::WARNING|Logger::ERROR|Logger::CRITICAL|Logger::ALERT|Logger::EMERGENCY,
     *     level_name: 'DEBUG'|'INFO'|'NOTICE'|'WARNING'|'ERROR'|'CRITICAL'|'ALERT'|'EMERGENCY',
     *     channel: string,
     *     datetime: \DateTimeImmutable,
     *     context: array<string|int, mixed>,
     *     extra: array<string|int, mixed>
     * } $record
     * @return array<string|int, mixed>
     * }
     */
    public function format(array|LogRecord $record): array
    {
        $normalized = $this->normalize(\is_array($record) ? $record : $record->toArray());

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

        return [
            'level' => $this->loggerMode === RoadRunnerLogsMode::Development ? \strtoupper($level) : $level,
            'ts' => $ts,
            'logger' => $this->loggerPrefix . $record['channel'],
            'msg' => $record['message'],
        ]
            + ($normalized['context'] ?? [])
            + ($normalized['extra'] ?? []);
    }

    protected function normalizeException(\Throwable $e, int $depth = 0): array
    {
        $normalized = parent::normalizeException($e, $depth);

        if (isset($normalized['trace']) && \is_array($normalized['trace'])) {
            $normalized['trace'] = \array_slice($normalized['trace'], 0, $this->traceCount);
        }

        return $normalized;
    }
}
