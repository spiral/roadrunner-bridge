<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Monolog\Handler\ErrorLogHandler;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use RoadRunner\Logger\Logger;
use Spiral\Monolog\Bootloader\MonologBootloader;
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

    public function init(MonologBootloader $bootloader, Handler $handler): void
    {
        $bootloader->addHandler('roadrunner', $handler);
    }

    private function initHandler(Logger $logger, RoadRunnerMode $mode, EnvironmentInterface $env): Handler
    {
        $fallbackHandler = $mode === RoadRunnerMode::Unknown ? new ErrorLogHandler() : null;

        return new Handler($logger, $fallbackHandler, $env->get('LOGGER_FORMAT', Handler::FORMAT));
    }
}
