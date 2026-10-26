<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Spiral\Boot\Injector\EnumInjector;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

final class RoadRunnerModeTest extends TestCase
{
    public static function roadRunnerModes(): array
    {
        return [
            'http' => ['http', RoadRunnerMode::Http],
            'tcp' => ['tcp', RoadRunnerMode::Unknown],
            'grpc' => ['grpc', RoadRunnerMode::Grpc],
            'temporal' => ['temporal', RoadRunnerMode::Temporal],
            'jobs' => ['jobs', RoadRunnerMode::Jobs],
            'test' => ['test', RoadRunnerMode::Unknown],
        ];
    }

    #[DataProvider('roadRunnerModes')]
    #[Test]
    public function detectMode(string $mode, RoadRunnerMode $expected): void
    {
        $this->getContainer()->bindSingleton(EnvironmentInterface::class, static fn() => new Environment([
            'RR_MODE' => $mode,
        ]), true);

        Assert::same($this->getContainer()->get(RoadRunnerMode::class), $expected);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->getContainer()->removeBinding(RoadRunnerMode::class);
        $this->getContainer()->bindInjector(RoadRunnerMode::class, EnumInjector::class);
    }
}
