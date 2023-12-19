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
    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            FactoryInterface::class => static fn(
                RPCInterface $rpc,
                SerializerInterface $serializer,
            ): FactoryInterface => new Factory($rpc, $serializer),

            SerializerInterface::class => DefaultSerializer::class,
        ];
    }

    public function defineBindings(): array
    {
        return [
            StorageInterface::class =>
            /** @param non-empty-string $driver */
            static fn(
                FactoryInterface $factory,
                string $driver,
            ): StorageInterface => $factory->select($driver),
        ];
    }


    public function init(BaseCacheBootloader $cacheBootloader): void
    {
        $cacheBootloader->registerTypeAlias(StorageInterface::class, 'roadrunner');
    }
}
