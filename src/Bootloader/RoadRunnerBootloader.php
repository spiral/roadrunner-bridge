<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface as GlobalEnvironmentInterface;
use Spiral\Core\Container;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;

final class RoadRunnerBootloader extends Bootloader
{
    public function init(Container $container)
    {
        //
        // Register RoadRunner Environment
        //
        $container->bindSingleton(EnvironmentInterface::class, Environment::class);
        $container->bindSingleton(
            Environment::class,
            static fn (GlobalEnvironmentInterface $env): EnvironmentInterface => new Environment($env->getAll())
        );

        //
        // Register RPC
        //
        $container->bindSingleton(RPCInterface::class, RPC::class);
        $container->bindSingleton(
            RPC::class,
            static fn (EnvironmentInterface $env): RPCInterface => new RPC(Relay::create($env->getRPCAddress()))
        );

        //
        // Register Worker
        //
        $container->bindSingleton(WorkerInterface::class, Worker::class);
        $container->bindSingleton(
            Worker::class,
            static fn (EnvironmentInterface $env): WorkerInterface => Worker::createFromEnvironment($env)
        );

        //
        // Register PSR Worker
        //
        $container->bindSingleton(PSR7WorkerInterface::class, PSR7Worker::class);

        $container->bindSingleton(PSR7Worker::class, static function (
            WorkerInterface $worker,
            ServerRequestFactoryInterface $requests,
            StreamFactoryInterface $streams,
            UploadedFileFactoryInterface $uploads
        ): PSR7WorkerInterface {
            return new PSR7Worker($worker, $requests, $streams, $uploads);
        });
    }
}
