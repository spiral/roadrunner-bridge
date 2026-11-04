<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger as Monologger;
use RoadRunner\Logger\Logger;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\RoadRunnerBridge\Logger\RawHandler;
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
        RoadRunnerLogsMode $logsMode,
        EnvironmentInterface $env,
    ): void {
        $bootloader->addHandler('roadrunner', $mode === RoadRunnerMode::Unknown
            ? new ErrorLogHandler()
            : self::handler($logger, $logsMode, $env));
    }

    private static function handler(Logger $logger, RoadRunnerLogsMode $logsMode, EnvironmentInterface $env): HandlerInterface
    {
        $prefix = (string) $env->get('RR_LOGGER_PREFIX');
        $level = Monologger::toMonologLevel($env->get('MONOLOG_DEFAULT_LEVEL', 'INFO'));

        return $logsMode === RoadRunnerLogsMode::Native
            ? new Handler($logger, $prefix, $level)
            : new RawHandler($logger, $prefix, $logsMode, $level);
    }
}
