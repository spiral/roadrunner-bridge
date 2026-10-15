<?php

declare(strict_types=1);

namespace Spiral\Tests\Config;

use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\Tests\TestCase;

final class TcpConfigTest extends TestCase
{
    public function testDebugMode(): void
    {
        $config = new TcpConfig([
            'debug' => true,
        ]);

        $this->assertTrue($config->isDebugMode());
    }

    public function testGetServices(): void
    {
        $config = new TcpConfig([
            'services' => ['foo', 'bar'],
        ]);

        $this->assertSame(['foo', 'bar'], $config->getServices());
    }

    public function testGetNotExistsServices(): void
    {
        $config = new TcpConfig();

        $this->assertSame([], $config->getServices());
    }

    public function testGetInterceptors(): void
    {
        $config = new TcpConfig([
            'interceptors' => [
                'test' => ['foo', 'bar'],
            ],
        ]);

        $this->assertSame(['test' => ['foo', 'bar']], $config->getInterceptors());
    }

    public function testGetNotExistsInterceptors(): void
    {
        $config = new TcpConfig();

        $this->assertSame([], $config->getInterceptors());
    }
}
