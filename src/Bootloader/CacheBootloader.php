<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Cache\Bootloader\CacheBootloader as BaseCacheBootloader;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\FactoryInterface;
use Spiral\RoadRunner\KeyValue\Serializer\DefaultSerializer;
use Spiral\RoadRunner\KeyValue\Serializer\SerializerInterface;
use Spiral\RoadRunner\KeyValue\StorageInterface;

final class CacheBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
    ];

    protected const SINGLETONS = [
        FactoryInterface::class => [self::class, 'initStorageFactory'],
        SerializerInterface::class => DefaultSerializer::class,
    ];

    protected const BINDINGS = [
        StorageInterface::class => [self::class, 'initDefaultStorage'],
    ];

    private function initStorageFactory(RPCInterface $rpc, SerializerInterface $serializer): FactoryInterface
    {
        return new Factory($rpc, $serializer);
    }

    /**
     * @param non-empty-string $driver
     */
    private function initDefaultStorage(FactoryInterface $factory, string $driver): StorageInterface
    {
        return $factory->select($driver);
    }

    public function init(BaseCacheBootloader $cacheBootloader): void
    {
        $cacheBootloader->registerTypeAlias(StorageInterface::class, 'roadrunner');
    }
}
