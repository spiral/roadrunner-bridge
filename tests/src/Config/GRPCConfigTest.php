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
            'interceptors' => ['foo', 'bar'],
        ]);

        $this->assertSame(['foo', 'bar'], $config->getInterceptors());
    }

    public function testGetNotExistsInterceptors(): void
    {
        $config = new GRPCConfig();

        $this->assertSame([], $config->getInterceptors());
    }

    public function testGetGeneratedPath(): void
    {
        $config = new GRPCConfig([
            'generatedPath' => 'foo',
        ]);

        $this->assertSame('foo', $config->getGeneratedPath());
    }

    public function testGetNonExistsGeneratedPath(): void
    {
        $config = new GRPCConfig();

        $this->assertNull($config->getGeneratedPath());
    }

    public function testGetNamespace(): void
    {
        $config = new GRPCConfig([
            'namespace' => 'foo',
        ]);

        $this->assertSame('foo', $config->getNamespace());
    }

    public function testGetNonExistsNamespace(): void
    {
        $config = new GRPCConfig();

        $this->assertNull($config->getNamespace());
    }

    public function testGetServicesBasePath(): void
    {
        $config = new GRPCConfig([
            'servicesBasePath' => 'foo',
        ]);

        $this->assertSame('foo', $config->getServicesBasePath());
    }

    public function testGetNonExistsServicesBasePath(): void
    {
        $config = new GRPCConfig();

        $this->assertNull($config->getServicesBasePath());
    }
}
