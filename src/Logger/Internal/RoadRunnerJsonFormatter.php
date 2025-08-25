<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger\Internal;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;

/**
 * RoadRunner JSON formatter for Monolog that formats log records according to RoadRunner's logging standards.
 *
 * This formatter extends Monolog's NormalizerFormatter to provide RoadRunner-specific JSON formatting.
 * It handles different logging modes (production vs development) and provides configurable options
 * for logger prefixes, trace counts, and date formatting.
 *
 * The formatter produces JSON output compatible with RoadRunner's logging infrastructure and can
 * be configured to output either human-readable timestamps (development mode) or Unix timestamps
 * with microseconds (production mode).
 *
 * @internal
 */
final class RoadRunnerJsonFormatter extends NormalizerFormatter
{
    /**
     * Create a new RoadRunner JSON formatter instance.
     *
     * @param string $loggerPrefix Optional prefix to prepend to logger channel names. Useful for
     *        namespacing logs in multi-application environments.
     * @param RoadRunnerLogsMode $loggerMode The logging mode that determines timestamp format and log level
     *        representation. In development mode, provides human-readable timestamps and uppercase log levels.
     *        In production mode, uses Unix timestamps with microseconds and lowercase log levels.
     * @param int $traceCount Maximum number of stack trace entries to include when logging
     *        exceptions. Defaults to 5 to prevent excessive log size.
     * @param string|null $dateFormat Custom date format string. If null, uses the parent class default format.
     *        This parameter is passed to the parent NormalizerFormatter.
     */
    public function __construct(
        private readonly string $loggerPrefix = '',
        private readonly RoadRunnerLogsMode $loggerMode = RoadRunnerLogsMode::Production,
        private readonly int $traceCount = 5,
        ?string $dateFormat = null,
    ) {
        parent::__construct($dateFormat);
    }

    /**
     * Format a log record into a RoadRunner-compatible JSON structure.
     *
     * This method converts Monolog log records into a standardized format that RoadRunner
     * can process. The output includes essential fields like level, timestamp, logger name,
     * and message, along with any context and extra data from the original record.
     *
     * The formatting behavior varies based on the configured logger mode:
     * - Development mode: Human-readable timestamps (RFC3339) and uppercase log levels
     * - Production mode: Unix timestamps with microseconds and lowercase log levels
     *
     * @param LogRecord|array{
     *     message: string,
     *     level: Logger::DEBUG|Logger::INFO|Logger::NOTICE|Logger::WARNING|Logger::ERROR|Logger::CRITICAL|Logger::ALERT|Logger::EMERGENCY,
     *     level_name: 'DEBUG'|'INFO'|'NOTICE'|'WARNING'|'ERROR'|'CRITICAL'|'ALERT'|'EMERGENCY',
     *     channel: string,
     *     datetime: \DateTimeImmutable,
     *     context?: array<array-key, mixed>,
     *     extra?: array<array-key, mixed>
     * } $record The log record to format, either as a LogRecord object or array
     *
     * @return array{
     *     level: non-empty-string,
     *     ts: non-empty-string,
     *     logger: non-empty-string,
     *     msg: string,
     *     ...
     * } The formatted log record as an array with the following structure:
     *   - level: The log level (string)
     *   - ts: Timestamp in the configured format
     *   - logger: Logger name with optional prefix
     *   - msg: The log message
     *   - Additional context and extra data merged from the original record
     *
     * @throws \InvalidArgumentException If the record structure is invalid
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

    /**
     * Normalize exception data with configurable trace depth limit.
     *
     * This method overrides the parent implementation to limit the number of stack trace
     * entries included in the log output. This prevents excessive log size while still
     * providing useful debugging information.
     *
     * The trace count limit is controlled by the `$traceCount` constructor parameter,
     * which defaults to 5 entries.
     *
     * @param \Throwable $e The exception to normalize
     * @param int $depth The current recursion depth (used by parent implementation)
     *
     * @return array<array-key, string|int|array<string|int|array<string>>> The normalized exception data with limited stack trace
     */
    protected function normalizeException(\Throwable $e, int $depth = 0): array
    {
        $normalized = parent::normalizeException($e, $depth);

        if (isset($normalized['trace']) && \is_array($normalized['trace'])) {
            $normalized['trace'] = \array_slice($normalized['trace'], 0, $this->traceCount);
        }

        return $normalized;
    }
}
