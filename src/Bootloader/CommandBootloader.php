<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Core\Container;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunnerBridge\Console\Command\Cache;
use Spiral\RoadRunnerBridge\Console\Command\GRPC;
use Spiral\RoadRunnerBridge\Console\Command\Queue;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;

final class CommandBootloader extends Bootloader
{
    public function init(ConsoleBootloader $console, Container $container): void
    {
        $this->configureExtensions($console, $container);
    }

    private function configureExtensions(ConsoleBootloader $console, Container $container): void
    {
        if ($container->has(JobsInterface::class)) {
            $this->configureJobs($console);
        }

        if ($container->has(CacheStorageProviderInterface::class)) {
            $this->configureCache($console);
        }

        if ($container->has(LocatorInterface::class)) {
            $this->configureGrpc($console);
        }
    }

    private function configureJobs(ConsoleBootloader $console): void
    {
        $console->addCommand(Queue\PauseCommand::class);
        $console->addCommand(Queue\ResumeCommand::class);
        $console->addCommand(Queue\ListCommand::class);

        $console->addCommand(Queue\DeprecatedPauseCommand::class);
        $console->addCommand(Queue\DeprecatedResumeCommand::class);
        $console->addCommand(Queue\DeprecatedListCommand::class);
    }

    private function configureCache(ConsoleBootloader $console): void
    {
        $console->addCommand(Cache\ClearCommand::class);
    }

    private function configureGrpc(ConsoleBootloader $console): void
    {
        $console->addCommand(GRPC\GenerateCommand::class);
        $console->addCommand(GRPC\ListCommand::class);
    }
}
