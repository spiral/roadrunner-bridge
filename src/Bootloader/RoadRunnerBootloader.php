<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface as GlobalEnvironmentInterface;
use Spiral\Core\Container;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\Codec\JsonCodec;
use Spiral\Goridge\RPC\Codec\MsgpackCodec;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\CodecInterface;
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
        $container->bindSingleton(EnvironmentInterface::class, Environment::class);
        $container->bindSingleton(
            Environment::class,
            static function (GlobalEnvironmentInterface $env): EnvironmentInterface {
                return new Environment($env->getAll());
            }
        );

        //
        // Register RPC
        //
        $container->bindSingleton(RPCInterface::class, RPC::class);
        $container->bindSingleton(RPC::class, static function (EnvironmentInterface $env, GlobalEnvironmentInterface $globalEnv): RPCInterface {
            return new RPC(
                Relay::create($env->getRPCAddress()),
                self::withCodec($globalEnv),
            );
        });

        //
        // Register Worker
        //
        $container->bindSingleton(WorkerInterface::class, Worker::class);
        $container->bindSingleton(Worker::class, static function (EnvironmentInterface $env): WorkerInterface {
            return Worker::createFromEnvironment($env);
        });

        //
        // Register PSR Worker
        //
        $container->bindSingleton(PSR7WorkerInterface::class, PSR7Worker::class);

        $container->bindSingleton(PSR7Worker::class, static function (
            WorkerInterface $worker,
            ServerRequestFactory $requests,
            StreamFactory $streams,
            UploadedFileFactory $uploads
        ): PSR7WorkerInterface {
            return new PSR7Worker($worker, $requests, $streams, $uploads);
        });
    }

    private static function withCodec(GlobalEnvironmentInterface $env): CodecInterface
    {
        switch ($env->get('RPC_CODEC')) {
            case 'proto':
            case 'protobuf':
                return new ProtobufCodec();
            case 'msgpack':
                return new MsgpackCodec();
            default:
                return new JsonCodec();
        }
    }
}
