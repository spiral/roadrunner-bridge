<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface as GlobalEnvironmentInterface;
use Spiral\Core\Container;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\Http\Diactoros\ServerRequestFactory;
use Spiral\Http\Diactoros\StreamFactory;
use Spiral\Http\Diactoros\UploadedFileFactory;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;

final class RoadRunnerBootloader extends Bootloader
{
    public function boot(Container $container)
    {
        //
        // Register RoadRunner Environment
        //
        $environmentRegistrar = static function (GlobalEnvironmentInterface $env): EnvironmentInterface {
            return new Environment($env->getAll());
        };

        $container->bindSingleton(EnvironmentInterface::class, $environmentRegistrar);
        $container->bindSingleton(Environment::class, $environmentRegistrar);

        //
        // Register RPC
        //
        $rpcRegistrar = static function (EnvironmentInterface $env): RPCInterface {
            return RPC::create($env->getRPCAddress());
        };

        $container->bindSingleton(RPCInterface::class, $rpcRegistrar);
        $container->bindSingleton(RPC::class, $rpcRegistrar);

        //
        // Register Worker
        //
        $workerRegistrar = static function (EnvironmentInterface $env): WorkerInterface {
            return Worker::createFromEnvironment($env);
        };

        $container->bindSingleton(WorkerInterface::class, $workerRegistrar);
        $container->bindSingleton(Worker::class, $workerRegistrar);

        //
        // Register PSR Worker
        //
        $registrar = static function (
            WorkerInterface $worker,
            ServerRequestFactory $requests,
            StreamFactory $streams,
            UploadedFileFactory $uploads
        ): PSR7WorkerInterface {
            return new PSR7Worker(
                $worker,
                $requests,
                $streams,
                $uploads
            );
        };

        $container->bindSingleton(PSR7WorkerInterface::class, $registrar);
        $container->bindSingleton(PSR7Worker::class, $registrar);
    }
}
