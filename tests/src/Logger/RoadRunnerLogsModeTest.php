<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger;

use Spiral\Boot\Environment;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Test;

final class RoadRunnerLogsModeTest
{
    #[Test]
    #[DataSet([null, RoadRunnerLogsMode::Native], 'unset')]
    #[DataSet(['', RoadRunnerLogsMode::Native], 'empty')]
    #[DataSet(['native', RoadRunnerLogsMode::Native])]
    #[DataSet(['production', RoadRunnerLogsMode::Production])]
    #[DataSet(['development', RoadRunnerLogsMode::Development])]
    #[DataSet(['prod', RoadRunnerLogsMode::Production], 'alias prod')]
    #[DataSet(['dev', RoadRunnerLogsMode::Development], 'alias dev')]
    #[DataSet([' Development ', RoadRunnerLogsMode::Development], 'case and spaces')]
    #[DataSet(['json', RoadRunnerLogsMode::Native], 'unknown')]
    #[DataSet(['true', RoadRunnerLogsMode::Native], 'true')]
    public function detectsFromEnvironment(?string $value, RoadRunnerLogsMode $expected): void
    {
        $env = new Environment($value === null ? [] : ['RR_LOGGER_MODE' => $value]);

        Assert::same(RoadRunnerLogsMode::detect($env), $expected);
    }
}
