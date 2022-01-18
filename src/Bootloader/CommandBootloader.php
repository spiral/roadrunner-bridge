<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\ConsoleBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Container;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunnerBridge\Console\Command\Queue;

final class CommandBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        ConsoleBootloader::class,
    ];

    public function boot(
        ConsoleBootloader $console,
        ConfiguratorInterface $config,
        Container $container
    ): void {
        $this->configureExtensions($console, $container);
    }

    private function configureExtensions(ConsoleBootloader $console, Container $container): void
    {
        if ($container->has(JobsInterface::class)) {
            $this->configureJobs($console);
        }
    }

    private function configureJobs(ConsoleBootloader $console)
    {
        $console->addCommand(Queue\PauseCommand::class);
        $console->addCommand(Queue\ResumeCommand::class);
        $console->addCommand(Queue\ListCommand::class);
    }
}
