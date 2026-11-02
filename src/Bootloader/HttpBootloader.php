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
use Spiral\RoadRunnerBridge\Http\Internal\Dispatcher;

/**
 * @api
 */
final class HttpBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
            BaseHttpBootloader::class,
        ];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            PSR7Worker::class => PSR7WorkerInterface::class,

            PSR7WorkerInterface::class => static fn(
                WorkerInterface $worker,
                ServerRequestFactoryInterface $requests,
                StreamFactoryInterface $streams,
                UploadedFileFactoryInterface $uploads,
            ): PSR7WorkerInterface => new PSR7Worker($worker, $requests, $streams, $uploads),
        ];
    }

    public function boot(KernelInterface $kernel): void
    {
        /** @psalm-suppress InvalidArgument KernelInterface lacks the class-name form the kernel accepts */
        $kernel->addDispatcher(Dispatcher::class);
    }
}
