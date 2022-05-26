<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\Patch\Set;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\Core\ConfigsInterface;
use Spiral\Bootloader as Framework;
use Spiral\Http\Bootloader\DiactorosBootloader;
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;

abstract class TestCase extends \Spiral\Testing\TestCase
{
    public function defineBootloaders(): array
    {
        return [
            RoadRunnerBridge\CacheBootloader::class,
            RoadRunnerBridge\GRPCBootloader::class,
            RoadRunnerBridge\HttpBootloader::class,
            RoadRunnerBridge\QueueBootloader::class,
            RoadRunnerBridge\BroadcastingBootloader::class,
            RoadRunnerBridge\RoadRunnerBootloader::class,
            RoadRunnerBridge\TcpBootloader::class,

            // Framework commands
            ConsoleBootloader::class,
            Framework\CommandBootloader::class,
            Framework\SnapshotsBootloader::class,
            Framework\Http\HttpBootloader::class,
            DiactorosBootloader::class,

            \Spiral\SendIt\Bootloader\MailerBootloader::class,

            RoadRunnerBridge\CommandBootloader::class,
        ];
    }

    public function rootDirectory(): string
    {
        return __DIR__.'/../';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanUpRuntimeDirectory();
    }

    protected function accessProtected(object $obj, string $prop)
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);

        return $property->getValue($obj);
    }

    public function getEnvironment(): EnvironmentInterface
    {
        return $this->getContainer()->get(EnvironmentInterface::class);
    }
}
