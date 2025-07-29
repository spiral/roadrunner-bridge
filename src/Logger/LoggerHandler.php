<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger as RoadRunnerLogger;

final class LoggerHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly RoadRunnerLogger $logger,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct(Logger::toMonologLevel($level), $bubble);
    }

    protected function write(array|LogRecord $record): void
    {
        $this->logger
            ->log($record['formatted'] . PHP_EOL);
    }
}
