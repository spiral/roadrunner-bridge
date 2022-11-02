<?php

declare(strict_types=1);

namespace Spiral\Tests\Config;

use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\Tests\TestCase;

final class GRPCConfigTest extends TestCase
{
    public function testGetBinaryPath(): void
    {
        $config = new GRPCConfig([
            'binaryPath' => 'foo',
        ]);

        $this->assertSame('foo', $config->getBinaryPath());
    }

    public function testGetNotExistsBinaryPath(): void
    {
        $config = new GRPCConfig();

        $this->assertNull($config->getBinaryPath());
    }

    public function testGetsServices(): void
    {
        $config = new GRPCConfig([
            'services' => ['foo', 'bar'],
        ]);

        $this->assertSame(['foo', 'bar'], $config->getServices());
    }

    public function testGetNotExistsServices(): void
    {
        $config = new GRPCConfig();

        $this->assertSame([], $config->getServices());
    }

    public function testGetInterceptors()
    {
        $config = new GRPCConfig([
            'interceptors' => ['foo', 'bar']
        ]);

        $this->assertSame(['foo', 'bar'], $config->getInterceptors());
    }

    public function testGetNotExistsInterceptors(): void
    {
        $config = new GRPCConfig();

        $this->assertSame([], $config->getInterceptors());
    }
}
