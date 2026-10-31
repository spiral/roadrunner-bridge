<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\Core\Container;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\Command as GRPC;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\RoadRunnerBridge\Queue\Internal\Command as Queue;

/**
 * @api
 */
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

        if ($container->has(LocatorInterface::class)) {
            $this->configureGrpc($console);
        }
    }

    private function configureJobs(ConsoleBootloader $console): void
    {
        $console->addCommand(Queue\PauseCommand::class);
        $console->addCommand(Queue\ResumeCommand::class);
        $console->addCommand(Queue\ListCommand::class);
    }

    private function configureGrpc(ConsoleBootloader $console): void
    {
        $console->addCommand(GRPC\GenerateCommand::class);
        $console->addCommand(GRPC\ListCommand::class);
    }
}
