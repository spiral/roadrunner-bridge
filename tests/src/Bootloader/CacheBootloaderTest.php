<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Cache\Config\CacheConfig;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\FactoryInterface;
use Spiral\RoadRunner\KeyValue\StorageInterface;
use Spiral\Tests\TestCase;

final class CacheBootloaderTest extends TestCase
{
    public function testGetsCacheFactory(): void
    {
        $this->assertContainerBoundAsSingleton(
            FactoryInterface::class,
            Factory::class
        );
    }

    public function testGetsStorageInterface(): void
    {
        $this->assertInstanceOf(
            \Spiral\RoadRunner\KeyValue\Cache::class,
            $cache = $this->getContainer()->make(StorageInterface::class, ['driver' => 'memory'])
        );

        $this->assertSame('memory', $cache->getName());
    }

    public function testStorageAliasForRoadRunnerShouldBeRegistered(): void
    {
        /** @var CacheConfig $config */
        $config = $this->getContainer()->get(CacheConfig::class);

        $this->assertSame(StorageInterface::class, $config->offsetGet('typeAliases')['roadrunner']);
    }
}
