<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\ConsoleBootloader;
use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Set;
use Spiral\Core\Container;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunnerBridge\Console\Command\Cache;
use Spiral\RoadRunnerBridge\Console\Command\GRPC;
use Spiral\RoadRunnerBridge\Console\Command\Queue;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;


use Spiral\Command\GRPC as DeprecatedGRPC;

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
        $this->removeDeprecatedCommands($config);
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

    private function configureJobs(ConsoleBootloader $console)
    {
        $console->addCommand(Queue\PauseCommand::class);
        $console->addCommand(Queue\ResumeCommand::class);
        $console->addCommand(Queue\ListCommand::class);
    }

    private function configureCache(ConsoleBootloader $console)
    {
        $console->addCommand(Cache\ClearCommand::class);
    }

    private function configureGrpc(ConsoleBootloader $console)
    {
        $console->addCommand(GRPC\GenerateCommand::class);
        $console->addCommand(GRPC\ListCommand::class);
    }

    /**
     * @deprecated since 2.9
     */
    private function removeDeprecatedCommands(ConfiguratorInterface $config)
    {
        $commands = $config->getConfig('console')['commands'] ?? [];
        $filterCommands = [
            DeprecatedGRPC\GenerateCommand::class,
            DeprecatedGRPC\ListCommand::class,
        ];

        $config->modify(
            'console',
            new Set('commands', array_diff($commands, $filterCommands))
        );
    }
}
