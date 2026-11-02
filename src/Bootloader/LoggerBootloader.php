<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger as Monologger;
use RoadRunner\Logger\Logger;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * @api
 */
final class LoggerBootloader extends Bootloader
{
    #[\Override]
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
        $bootloader->addHandler('roadrunner', $mode === RoadRunnerMode::Unknown
            ? new ErrorLogHandler()
            : new Handler(
                logger: $logger,
                loggerPrefix: (string) $env->get('RR_LOGGER_PREFIX'),
                loggerMode: $loggerMode,
                level: Monologger::toMonologLevel($env->get('MONOLOG_DEFAULT_LEVEL', 'INFO')),
            ));
    }
}
