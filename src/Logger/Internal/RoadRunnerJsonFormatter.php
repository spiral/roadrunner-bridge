<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger\Internal;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;

/**
 * Formats a record as the JSON line of bridge 4.x: `level`, `ts`, `logger`, `msg`, then context and extra.
 *
 * Production: `ts` in Unix nanoseconds (microsecond precision) and a lower-case level;
 * development: `ts` in RFC 3339 and an upper-case level.
 *
 * @internal
 */
final class RoadRunnerJsonFormatter extends NormalizerFormatter
{
    /**
     * @param positive-int $traceCount Stack trace frames kept for an exception.
     */
    public function __construct(
        private readonly string $loggerPrefix = '',
        private readonly RoadRunnerLogsMode $loggerMode = RoadRunnerLogsMode::Production,
        private readonly int $traceCount = 5,
    ) {
        parent::__construct();
    }

    /**
     * @return array<array-key, mixed>
     */
    #[\Override]
    public function format(LogRecord $record): array
    {
        $normalized = $this->normalize($record->toArray());
        \assert(\is_array($normalized));

        $level = match ($record->level) {
            Level::Error, Level::Critical, Level::Alert, Level::Emergency => 'error',
            Level::Warning => 'warning',
            Level::Info, Level::Notice => 'info',
            Level::Debug => 'debug',
        };
        $development = $this->loggerMode === RoadRunnerLogsMode::Development;

        $context = $normalized['context'] ?? null;
        $extra = $normalized['extra'] ?? null;

        return [
            'level' => $development ? \strtoupper($level) : $level,
            'ts' => $record->datetime->format($development ? \DateTimeInterface::RFC3339 : 'Uu000'),
            'logger' => $this->loggerPrefix . $record->channel,
            'msg' => $record->message,
        ]
            + (\is_array($context) ? $context : [])
            + (\is_array($extra) ? $extra : []);
    }

    /**
     * @return array<array-key, mixed>
     */
    #[\Override]
    protected function normalizeException(\Throwable $e, int $depth = 0): array
    {
        $normalized = parent::normalizeException($e, $depth);

        if (isset($normalized['trace']) && \is_array($normalized['trace'])) {
            $normalized['trace'] = \array_slice($normalized['trace'], 0, $this->traceCount);
        }

        return $normalized;
    }
}
