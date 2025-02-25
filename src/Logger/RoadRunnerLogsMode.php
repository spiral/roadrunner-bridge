<?php

namespace Spiral\RoadRunnerBridge\Logger;

use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\Injector\InjectableEnumInterface;
use Spiral\Boot\Injector\ProvideFrom;

/**
 * @see https://docs.roadrunner.dev/docs/logging-and-observability/logger#modes
 */
#[ProvideFrom(method: 'detect')]
enum RoadRunnerLogsMode: string implements InjectableEnumInterface
{
    case Production = 'production';
    case Development = 'development';

    public static function detect(EnvironmentInterface $environment): self
    {
        $value = $environment->get('RR_LOGGER_MODE', '');

        return RoadRunnerLogsMode::tryFrom($value) ?? RoadRunnerLogsMode::Production;
    }
}
