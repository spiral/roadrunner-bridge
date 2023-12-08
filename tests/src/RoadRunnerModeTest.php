<?php

declare(strict_types=1);

namespace Spiral\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Boot\Injector\EnumInjector;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

final class RoadRunnerModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->getContainer()->removeBinding(RoadRunnerMode::class);
        $this->getContainer()->bindInjector(RoadRunnerMode::class, EnumInjector::class);
    }

    #[DataProvider('roadRunnerModes')]
    public function testDetectMode(string $mode, RoadRunnerMode $expected): void
    {
        $this->getContainer()->bindSingleton(EnvironmentInterface::class, static fn () => new Environment([
            'RR_MODE' => $mode,
        ]), true);

        $this->assertSame($expected, $this->getContainer()->get(RoadRunnerMode::class));
    }

    public static function roadRunnerModes(): array
    {
        return [
            'http' => ['http', RoadRunnerMode::Http],
            'tcp' => ['tcp', RoadRunnerMode::Tcp],
            'grpc' => ['grpc', RoadRunnerMode::Grpc],
            'temporal' => ['temporal', RoadRunnerMode::Temporal],
            'jobs' => ['jobs', RoadRunnerMode::Jobs],
            'test' => ['test', RoadRunnerMode::Unknown],
        ];
    }
}
