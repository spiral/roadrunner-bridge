<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface as GlobalEnvironmentInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
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
    ];

    public function init(AbstractKernel $kernel): void
    {
        // Register Fallback Dispatcher after all dispatchers
        // It will be called if no other dispatcher can handle RoadRunner request
        $kernel->bootstrapped(static function (FallbackDispatcher $dispatcher, KernelInterface $kernel): void {
            $kernel->addDispatcher($dispatcher);
        });
    }

    private function initEnvironment(GlobalEnvironmentInterface $env): EnvironmentInterface
    {
        return new Environment($env->getAll());
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    private function initRPC(EnvironmentInterface $env): RPCInterface
    {
        return new RPC(Relay::create($env->getRPCAddress()));
    }

    private function initWorker(EnvironmentInterface $env): WorkerInterface
    {
        return Worker::createFromEnvironment($env);
    }
}
