<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use RoadRunner\Logger\Logger;
use Spiral\Boot\EnvironmentInterface;
use Spiral\RoadRunnerBridge\Logger\Formatter\RoadRunnerJsonFormatter;

final class LoggerHandlerFactory
{
    public function __construct(
        private readonly Logger $logger,
        private readonly RoadRunnerLogsMode $loggerMode,
        private readonly EnvironmentInterface $env,
    ) {}

    public function create(int|string|Level $level = Level::Debug, bool $bubble = true): HandlerInterface
    {
        $formatter = new RoadRunnerJsonFormatter(
            (string) $this->env->get('RR_LOGGER_PREFIX'),
            $this->loggerMode,
        );

        $handler = new LoggerHandler($this->logger, $level, $bubble);
        $handler->setFormatter($formatter);

        return $handler;
    }
}
