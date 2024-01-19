<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Bootloader\Http\HttpBootloader as BaseHttpBootloader;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Http\Dispatcher;
use Spiral\RoadRunnerBridge\Http\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Http\LogErrorHandler;

final class HttpBootloader extends Bootloader
{
    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
            BaseHttpBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            ErrorHandlerInterface::class => LogErrorHandler::class,
            PSR7Worker::class => PSR7WorkerInterface::class,

            PSR7WorkerInterface::class => static fn (
                WorkerInterface $worker,
                ServerRequestFactoryInterface $requests,
                StreamFactoryInterface $streams,
                UploadedFileFactoryInterface $uploads,
            ): PSR7WorkerInterface => new PSR7Worker($worker, $requests, $streams, $uploads),
        ];
    }

    public function boot(KernelInterface $kernel): void
    {
        $kernel->addDispatcher(Dispatcher::class);
    }
}
