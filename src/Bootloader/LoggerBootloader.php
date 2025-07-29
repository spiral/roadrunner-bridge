<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\RoadRunnerBridge\Logger\LoggerHandlerFactory;
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
        RoadRunnerMode $mode,
        EnvironmentInterface $env,
        LoggerHandlerFactory $handlerFactory,
    ): void {
        $bootloader->addHandler('roadrunner', $mode === RoadRunnerMode::Unknown
            ? new ErrorLogHandler()
            : $handlerFactory->create(
                level: Logger::toMonologLevel($env->get('MONOLOG_DEFAULT_LEVEL', 'INFO')),
            ));
    }
}
