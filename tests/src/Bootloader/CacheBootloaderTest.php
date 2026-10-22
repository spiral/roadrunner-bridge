<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Cache\Config\CacheConfig;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\FactoryInterface;
use Spiral\RoadRunner\KeyValue\StorageInterface;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class CacheBootloaderTest extends TestCase
{
    #[Test]
    public function getsCacheFactory(): void
    {
        $this->assertContainerBoundAsSingleton(
            FactoryInterface::class,
            Factory::class,
        );
    }

    #[Test]
    public function getsStorageInterface(): void
    {
        Assert::instanceOf($cache = $this->getContainer()->make(StorageInterface::class, ['driver' => 'memory']), \Spiral\RoadRunner\KeyValue\Cache::class);

        Assert::same($cache->getName(), 'memory');

        Assert::instanceOf($cache = $this->getContainer()->make(StorageInterface::class, ['driver' => 'test']), \Spiral\RoadRunner\KeyValue\Cache::class);

        Assert::same($cache->getName(), 'test');
    }

    #[Test]
    public function storageAliasForRoadRunnerShouldBeRegistered(): void
    {
        /** @var CacheConfig $config */
        $config = $this->getContainer()->get(CacheConfig::class);

        Assert::same($config->offsetGet('typeAliases')['roadrunner'], StorageInterface::class);
    }
}
