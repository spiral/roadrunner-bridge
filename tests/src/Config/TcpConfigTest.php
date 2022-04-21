<?php

declare(strict_types=1);

namespace Spiral\Tests\Config;

use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\Tests\TestCase;

final class TcpConfigTest extends TestCase
{
    public function testDebugMode(): void
    {
        $config = new TCPConfig([
            'debug' => true,
        ]);

        $this->assertTrue($config->isDebugMode());
    }

    public function testGetServices(): void
    {
        $config = new TCPConfig([
            'services' => ['foo', 'bar'],
        ]);

        $this->assertSame(['foo', 'bar'], $config->getServices());
    }

    public function testGetNotExistsServices(): void
    {
        $config = new TCPConfig();

        $this->assertSame([], $config->getServices());
    }

    public function testGetInterceptors(): void
    {
        $config = new TCPConfig([
            'interceptors' => [
                'test' => ['foo', 'bar'],
            ],
        ]);

        $this->assertSame(['test' => ['foo', 'bar']], $config->getInterceptors());
    }

    public function testGetNotExistsInterceptors(): void
    {
        $config = new TCPConfig();

        $this->assertSame([], $config->getInterceptors());
    }
}
