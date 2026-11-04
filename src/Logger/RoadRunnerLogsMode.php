<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\Injector\InjectableEnumInterface;
use Spiral\Boot\Injector\ProvideFrom;

/**
 * How the `roadrunner` Monolog handler writes records, taken from the `RR_LOGGER_MODE` environment variable.
 *
 * RoadRunner does not set the variable: the application does, e.g. in `server.env` of `.rr.yaml`.
 * - {@see self::Native} (unset or `native`): {@see Handler}, RoadRunner renders the records by its `logs` config;
 * - {@see self::Production}, {@see self::Development}: {@see RawHandler}, the JSON lines of bridge 4.x.
 *
 * @api
 */
#[ProvideFrom(method: 'detect')]
enum RoadRunnerLogsMode: string implements InjectableEnumInterface
{
    case Native = 'native';
    case Production = 'production';
    case Development = 'development';

    public static function detect(EnvironmentInterface $environment): self
    {
        $value = $environment->get('RR_LOGGER_MODE', '');

        return self::tryFrom(\is_string($value) ? \strtolower($value) : '') ?? self::Native;
    }
}
