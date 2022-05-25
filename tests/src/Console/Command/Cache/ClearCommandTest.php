<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Cache;

use Psr\SimpleCache\CacheInterface;
use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Tests\ConsoleTestCase;

final class ClearCommandTest extends ConsoleTestCase
{
    /** @var CacheStorageProviderInterface|\Mockery\LegacyMockInterface|\Mockery\MockInterface */
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = \Mockery::mock(CacheStorageProviderInterface::class);
        $this->getContainer()->bind(CacheStorageProviderInterface::class, $this->provider);
    }

    public function testCacheWithDefaultStorageShouldBeCleared(): void
    {
        $this->provider->shouldReceive('storage')
            ->with(null)
            ->once()
            ->andReturn($storage = \Mockery::mock(CacheInterface::class));

        $storage->shouldReceive('clear')->once();

        $this->assertStringContainsString(
            'Cache has been cleared.',
            $this->runCommand('cache:clear')
        );
    }

    public function testCacheWithSpecificStorageShouldBeCleared(): void
    {
        $this->provider->shouldReceive('storage')
            ->with('foo')
            ->once()
            ->andReturn($storage = \Mockery::mock(CacheInterface::class));

        $storage->shouldReceive('clear')->once();

        $this->assertStringContainsString(
            'Cache has been cleared.',
            $this->runCommand('cache:clear', ['storage' => 'foo'])
        );
    }
}
