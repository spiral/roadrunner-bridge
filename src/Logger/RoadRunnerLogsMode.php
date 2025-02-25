<?php

namespace Spiral\RoadRunnerBridge\Logger;

use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\Injector\InjectableEnumInterface;

/**
 * @see https://docs.roadrunner.dev/docs/logging-and-observability/logger#modes
 */
enum RoadRunnerLogsMode: string implements InjectableEnumInterface
{
    case Production = 'production';
    case Development = 'development';

    public static function detect(EnvironmentInterface $environment): self
    {
        $value = $environment->get('RR_LOGGER_MODE');

        return RoadRunnerLogsMode::tryFrom($value) ?? RoadRunnerLogsMode::Production;
    }
}
