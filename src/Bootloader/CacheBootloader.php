<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Cache\Bootloader\CacheBootloader as BaseCacheBootloader;
use Spiral\Core\Container;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\FactoryInterface;
use Spiral\RoadRunner\KeyValue\Serializer\DefaultSerializer;
use Spiral\RoadRunner\KeyValue\StorageInterface;

final class CacheBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
        BaseCacheBootloader::class,
    ];

    public function boot(Container $container, BaseCacheBootloader $cacheBootloader): void
    {
        $container->bindSingleton(FactoryInterface::class, static function (RPCInterface $rpc) {
            return new Factory($rpc, new DefaultSerializer());
        });

        $container->bindSingleton(StorageInterface::class, static function (FactoryInterface $factory, string $driver) {
            return $factory->select($driver);
        });

        $cacheBootloader->registerTypeAlias(StorageInterface::class, 'roadrunner');
    }
}
