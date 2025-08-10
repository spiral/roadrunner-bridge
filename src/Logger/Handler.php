<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger as RoadRunnerLogger;
use Spiral\RoadRunnerBridge\Logger\Formatter\RoadRunnerJsonFormatter;

final class Handler extends AbstractProcessingHandler
{
    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function __construct(
        private readonly RoadRunnerLogger $logger,
        string $loggerPrefix = '',
        RoadRunnerLogsMode $loggerMode = RoadRunnerLogsMode::Production,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct(Logger::toMonologLevel($level), $bubble);

        $this->setFormatter(new RoadRunnerJsonFormatter($loggerPrefix, $loggerMode));
    }

    protected function write(array|LogRecord $record): void
    {
        $this->logger
            ->log($record['formatted'] . PHP_EOL);
    }
}
