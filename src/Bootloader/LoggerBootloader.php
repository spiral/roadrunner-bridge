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
    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            Handler::class => static function (
                Logger $logger,
                RoadRunnerMode $mode,
                EnvironmentInterface $env,
            ): Handler {
                $fallbackHandler = $mode === RoadRunnerMode::Unknown ? new ErrorLogHandler() : null;

                return new Handler(
                    logger: $logger,
                    fallbackHandler: $fallbackHandler,
                    formatter: $env->get('LOGGER_FORMAT', Handler::FORMAT),
                );
            },
        ];
    }

    public function init(MonologBootloader $bootloader, Handler $handler): void
    {
        $bootloader->addHandler('roadrunner', $handler);
    }
}
