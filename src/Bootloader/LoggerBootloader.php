<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Monolog\Handler\ErrorLogHandler;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use RoadRunner\Logger\Logger;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

final class LoggerBootloader extends Bootloader
{
    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
        ];
    }

    public function init(
        MonologBootloader $bootloader,
        Logger $logger,
        RoadRunnerMode $mode,
        EnvironmentInterface $env,
        RoadRunnerLogsMode $loggerMode,
    ): void {
        print_r($mode);
        $bootloader->addHandler('roadrunner', $mode === RoadRunnerMode::Unknown ? new ErrorLogHandler() : new Handler(
            logger: $logger,
            loggerPrefix: $env->get('RR_LOGGER_PREFIX', '') ?? '',
            loggerMode: $loggerMode,
        ));
    }
}
