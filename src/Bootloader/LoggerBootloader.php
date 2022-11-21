<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use RoadRunner\Logger\Logger;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

final class LoggerBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
    ];

    protected const SINGLETONS = [
        Handler::class => [self::class, 'initHandler'],
    ];

    private function initHandler(Logger $logger, RoadRunnerMode $mode, EnvironmentInterface $env): Handler
    {
        return new Handler($logger, $mode, $env->get('LOGGER_FORMAT', Handler::FORMAT));
    }
}
