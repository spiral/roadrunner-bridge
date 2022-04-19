<?php

declare(strict_types=1);

namespace Spiral\Tests\Config;

use Spiral\RoadRunnerBridge\Config\Exception\Tcp\InvalidInterceptorException;
use Spiral\RoadRunnerBridge\Config\Exception\Tcp\InvalidServiceException;
use Spiral\RoadRunnerBridge\Config\Exception\Tcp\ServiceNotFoundException;
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

    public function testGetService(): void
    {
        $config = new TCPConfig([
            'services' => [
                'server' => 'foo',
            ],
        ]);

        $this->assertSame('foo', $config->getService('server'));
    }

    public function testServiceIsNotExist(): void
    {
        $config = new TCPConfig([
            'services' => [
                'server' => 'foo',
            ],
        ]);

        $this->expectException(ServiceNotFoundException::class);
        $config->getService('bar');
    }

    public function testInvalidService(): void
    {
        $config = new TCPConfig([
            'services' => [
                'server' => false,
            ],
        ]);

        $this->expectException(InvalidServiceException::class);
        $config->getService('server');
    }

    public function testGetInterceptors(): void
    {
        $config = new TCPConfig([
            'interceptors' => [
                'test' => ['foo', 'bar'],
            ],
        ]);

        $this->assertSame(['foo', 'bar'], $config->getInterceptors('test'));
    }

    public function testGetNotExistsInterceptors(): void
    {
        $config = new TCPConfig();

        $this->assertSame([], $config->getInterceptors('test'));
    }

    public function testInvalidInterceptor(): void
    {
        $config = new TCPConfig([
            'interceptors' => [
                'test' => false,
            ],
        ]);

        $this->expectException(InvalidInterceptorException::class);
        $config->getInterceptors('test');
    }
}
