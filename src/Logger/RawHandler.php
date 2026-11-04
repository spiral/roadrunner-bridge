<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger as RoadRunnerLogger;
use Spiral\RoadRunnerBridge\Logger\Internal\RoadRunnerJsonFormatter;

/**
 * Sends Monolog records to RoadRunner as the ready JSON lines of bridge 4.x, through the raw app-logger `log()`.
 *
 * RoadRunner prints such a line as is: it applies neither `logs.mode` nor `logs.level`. The channel, with
 * the optional prefix, goes to `logger`. Use {@see Handler} to let RoadRunner render the records itself.
 *
 * @api
 */
final class RawHandler extends AbstractProcessingHandler
{
    /**
     * @param RoadRunnerLogsMode $loggerMode `Production` or `Development`: the format of `ts` and the level.
     *
     * @psalm-suppress ArgumentTypeCoercion Monolog validates the level and throws on an unknown one
     */
    public function __construct(
        private readonly RoadRunnerLogger $logger,
        string $loggerPrefix = '',
        RoadRunnerLogsMode $loggerMode = RoadRunnerLogsMode::Production,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        $loggerMode === RoadRunnerLogsMode::Native and throw new \InvalidArgumentException(
            'RawHandler formats lines in the production or development mode; use Handler for the native one.',
        );

        parent::__construct(Logger::toMonologLevel($level), $bubble);

        $this->setFormatter(new RoadRunnerJsonFormatter($loggerPrefix, $loggerMode));
    }

    #[\Override]
    protected function write(LogRecord $record): void
    {
        // A formatter set with setFormatter() may return a ready string: it is sent as is, as in 4.x
        $line = \is_string($record->formatted)
            ? $record->formatted
            : \json_encode($record->formatted, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        $this->logger->log($line . \PHP_EOL);
    }
}
