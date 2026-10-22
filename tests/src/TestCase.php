<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Spiral\Boot\EnvironmentInterface;
use Spiral\Bootloader as Framework;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\Nyholm\Bootloader\NyholmBootloader;
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
use Testo\Lifecycle\AfterTest;

abstract class TestCase extends \Spiral\Testing\TestCase
{
    public function defineBootloaders(): array
    {
        return [
            RoadRunnerBridge\CacheBootloader::class,
            RoadRunnerBridge\GRPCBootloader::class,
            RoadRunnerBridge\HttpBootloader::class,
            RoadRunnerBridge\QueueBootloader::class,
            RoadRunnerBridge\RoadRunnerBootloader::class,
            RoadRunnerBridge\MetricsBootloader::class,
            RoadRunnerBridge\LoggerBootloader::class,
            RoadRunnerBridge\CentrifugoBootloader::class,
            RoadRunnerBridge\ScaffolderBootloader::class,

            // Framework commands
            ConsoleBootloader::class,
            Framework\CommandBootloader::class,
            Framework\SnapshotsBootloader::class,
            Framework\Http\HttpBootloader::class,
            NyholmBootloader::class,

            \Spiral\SendIt\Bootloader\MailerBootloader::class,

            RoadRunnerBridge\CommandBootloader::class,
        ];
    }

    public function rootDirectory(): string
    {
        return __DIR__ . '/../';
    }

    public function getEnvironment(): EnvironmentInterface
    {
        return $this->getContainer()->get(EnvironmentInterface::class);
    }

    #[AfterTest]
    protected function cleanUpRuntime(): void
    {
        $this->cleanUpRuntimeDirectory();
    }

    protected function accessProtected(object $obj, string $prop)
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);

        return $property->getValue($obj);
    }
}
