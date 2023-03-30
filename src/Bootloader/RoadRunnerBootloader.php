<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface as GlobalEnvironmentInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\FallbackDispatcher;

final class RoadRunnerBootloader extends Bootloader
{
    protected const SINGLETONS = [
        EnvironmentInterface::class => [self::class, 'initEnvironment'],
        Environment::class => EnvironmentInterface::class,

        RPC::class => RPCInterface::class,
        RPCInterface::class => [self::class, 'initRPC'],

        Worker::class => WorkerInterface::class,
        WorkerInterface::class => [self::class, 'initWorker'],

        PSR7Worker::class => PSR7WorkerInterface::class,
        PSR7WorkerInterface::class => [self::class, 'initPSR7Worker'],
    ];

    private function initEnvironment(GlobalEnvironmentInterface $env): EnvironmentInterface
    {
        return new Environment($env->getAll());
    }

    private function initRPC(EnvironmentInterface $env): RPCInterface
    {
        return new RPC(Relay::create($env->getRPCAddress()));
    }

    private function initWorker(EnvironmentInterface $env): WorkerInterface
    {
        return Worker::createFromEnvironment($env);
    }

    private function initPSR7Worker(
        WorkerInterface $worker,
        ServerRequestFactoryInterface $requests,
        StreamFactoryInterface $streams,
        UploadedFileFactoryInterface $uploads,
    ): PSR7WorkerInterface {
        return new PSR7Worker($worker, $requests, $streams, $uploads);
    }

    public function init(AbstractKernel $kernel): void
    {
        // Register Fallback Dispatcher after all dispatchers
        // It will be called if no other dispatcher can handle RoadRunner request
        $kernel->bootstrapped(static function (FallbackDispatcher $dispatcher, KernelInterface $kernel): void {
            $kernel->addDispatcher($dispatcher);
        });
    }
}
