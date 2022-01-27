<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\ServerBootloader;
use Spiral\Core\Container;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\FactoryInterface;
use Spiral\RoadRunner\KeyValue\Serializer\DefaultSerializer;
use Spiral\RoadRunner\KeyValue\StorageInterface;

final class CacheBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        // RoadRunnerBootloader::class,
        ServerBootloader::class,
        \Spiral\Bootloader\Cache\CacheBootloader::class,
    ];

    public function register(Container $container): void
    {
        $container->bindSingleton(FactoryInterface::class, static function (RPCInterface $rpc) {
            return new Factory($rpc, new DefaultSerializer());
        });

        $container->bindSingleton(StorageInterface::class, static function (FactoryInterface $factory, string $driver) {
            return $factory->select($driver);
        });
    }

    public function boot(
        \Spiral\Bootloader\Cache\CacheBootloader $cacheBootloader
    ): void {
        $cacheBootloader->registerTypeAlias(StorageInterface::class, 'roadrunner');
    }
}
