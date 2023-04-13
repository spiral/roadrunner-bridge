<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Bootloader\Http\HttpBootloader as BaseHttpBootloader;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Http\Dispatcher;
use Spiral\RoadRunnerBridge\Http\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Http\LogErrorHandler;

final class HttpBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
        BaseHttpBootloader::class,
    ];

    protected const SINGLETONS = [
        ErrorHandlerInterface::class => LogErrorHandler::class,
        PSR7Worker::class => PSR7WorkerInterface::class,
        PSR7WorkerInterface::class => [self::class, 'initPSR7Worker'],
    ];

    public function boot(KernelInterface $kernel, FactoryInterface $factory): void
    {
        $kernel->addDispatcher($factory->make(Dispatcher::class));
    }

    private function initPSR7Worker(
        WorkerInterface $worker,
        ServerRequestFactoryInterface $requests,
        StreamFactoryInterface $streams,
        UploadedFileFactoryInterface $uploads,
    ): PSR7WorkerInterface {
        return new PSR7Worker($worker, $requests, $streams, $uploads);
    }
}
