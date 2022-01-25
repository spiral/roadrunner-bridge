<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Bootloader\ServerBootloader;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Http\Dispatcher;
use Spiral\RoadRunnerBridge\Http\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Http\LogErrorHandler;

final class HttpBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        // RoadRunnerBootloader::class,
        ServerBootloader::class,
    ];

    protected const SINGLETONS = [
        ErrorHandlerInterface::class => LogErrorHandler::class,
    ];

    public function boot(KernelInterface $kernel, FactoryInterface $factory): void
    {
        $kernel->addDispatcher($factory->make(Dispatcher::class));
    }
}
