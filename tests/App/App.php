<?php

declare(strict_types=1);

namespace Spiral\App;

use Spiral\Boot\BootloadManager;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Bootloader as Framework;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\Console\Console;
use Spiral\Core\Container;
use Spiral\Http\Bootloader\DiactorosBootloader;
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
use Spiral\Framework\Kernel;

class App extends Kernel
{
    protected const LOAD = [
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

    /**
     * Get object from the container.
     */
    public function get(string $alias, string $context = null)
    {
        return $this->container->get($alias, $context);
    }

    public function getBootloadManager(): BootloadManager
    {
        return $this->bootloader;
    }

    public function console(): Console
    {
        return $this->get(Console::class);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function dir(string $alias): string
    {
        return $this->container->get(DirectoriesInterface::class)->get($alias);
    }
}
