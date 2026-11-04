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
 * The value is case-insensitive:
 * - `production` or `prod`, `development` or `dev`: {@see RawHandler}, the JSON lines of bridge 4.x;
 * - anything else, unset included: {@see self::Native}, {@see Handler}, RoadRunner renders the records
 *   by its `logs` config.
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

        return match (\is_string($value) ? \strtolower(\trim($value)) : '') {
            'production', 'prod' => self::Production,
            'development', 'dev' => self::Development,
            default => self::Native,
        };
    }
}
