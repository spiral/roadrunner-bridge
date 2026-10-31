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
use Spiral\RoadRunnerBridge\Bootloader\Internal\FallbackDispatcher;

/**
 * @api
 */
final class RoadRunnerBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            EnvironmentInterface::class => static fn(
                GlobalEnvironmentInterface $env,
            ): EnvironmentInterface => new Environment($env->getAll()),

            Environment::class => EnvironmentInterface::class,

            RPC::class => RPCInterface::class,
            RPCInterface::class =>
            /** @psalm-suppress ArgumentTypeCoercion The RPC address always has a default value */
                static fn(
                    EnvironmentInterface $env,
                ): RPCInterface => new RPC(Relay::create($env->getRPCAddress())),

            WorkerInterface::class => static fn(
                EnvironmentInterface $env,
            ): WorkerInterface => Worker::createFromEnvironment($env),

            Worker::class => WorkerInterface::class,
        ];
    }

    public function init(AbstractKernel $kernel): void
    {
        // Register Fallback Dispatcher after all dispatchers
        // It will be called if no other dispatcher can handle RoadRunner request
        $kernel->bootstrapped(static function (KernelInterface $kernel): void {
            /** @psalm-suppress InvalidArgument KernelInterface lacks the class-name form the kernel accepts */
            $kernel->addDispatcher(FallbackDispatcher::class);
        });
    }
}
