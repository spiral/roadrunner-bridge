<?php

declare(strict_types=1);

namespace Spiral\Tests\Config;

use Spiral\Core\Container\Autowire;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class GRPCConfigTest extends TestCase
{
    #[Test]
    public function getBinaryPaths(): void
    {
        $config = new GRPCConfig([
            'bufPath' => 'buf-path',
            'protocPath' => 'protoc-path',
            'pluginPath' => 'plugin-path',
        ]);

        Assert::same($config->getBufPath(), 'buf-path');
        Assert::same($config->getProtocPath(), 'protoc-path');
        Assert::same($config->getPluginPath(), 'plugin-path');
    }

    #[Test]
    public function getNotExistsBinaryPaths(): void
    {
        $config = new GRPCConfig();

        Assert::null($config->getBufPath());
        Assert::null($config->getProtocPath());
        Assert::null($config->getPluginPath());
    }

    #[Test]
    public function binaryPathKeyIsNotPluginPath(): void
    {
        $config = new GRPCConfig([
            'binaryPath' => 'foo',
        ]);

        Assert::null($config->getPluginPath());
    }

    #[Test]
    public function getBufTemplate(): void
    {
        $config = new GRPCConfig([
            'bufTemplate' => 'buf.gen.yaml',
        ]);

        Assert::same($config->getBufTemplate(), 'buf.gen.yaml');
    }

    #[Test]
    public function getNotExistsBufTemplate(): void
    {
        $config = new GRPCConfig();

        Assert::null($config->getBufTemplate());
    }

    #[Test]
    public function getsServices(): void
    {
        $config = new GRPCConfig([
            'services' => ['foo', 'bar'],
        ]);

        Assert::same($config->getServices(), ['foo', 'bar']);
    }

    #[Test]
    public function getNotExistsServices(): void
    {
        $config = new GRPCConfig();

        Assert::same($config->getServices(), []);
    }

    #[Test]
    public function getInterceptors(): void
    {
        $config = new GRPCConfig([
            'interceptors' => ['foo', 'bar'],
        ]);

        Assert::same($config->getInterceptors(), ['foo', 'bar']);
    }

    #[Test]
    public function getNotExistsInterceptors(): void
    {
        $config = new GRPCConfig();

        Assert::same($config->getInterceptors(), []);
    }

    #[Test]
    public function getGeneratedPath(): void
    {
        $config = new GRPCConfig([
            'generatedPath' => 'foo',
        ]);

        Assert::same($config->getGeneratedPath(), 'foo');
    }

    #[Test]
    public function getNonExistsGeneratedPath(): void
    {
        $config = new GRPCConfig();

        Assert::null($config->getGeneratedPath());
    }

    #[Test]
    public function getNamespace(): void
    {
        $config = new GRPCConfig([
            'namespace' => 'foo',
        ]);

        Assert::same($config->getNamespace(), 'foo');
    }

    #[Test]
    public function getNonExistsNamespace(): void
    {
        $config = new GRPCConfig();

        Assert::null($config->getNamespace());
    }

    #[Test]
    public function getServicesBasePath(): void
    {
        $config = new GRPCConfig([
            'servicesBasePath' => 'foo',
        ]);

        Assert::same($config->getServicesBasePath(), 'foo');
    }

    #[Test]
    public function getNonExistsServicesBasePath(): void
    {
        $config = new GRPCConfig();

        Assert::null($config->getServicesBasePath());
    }

    #[Test]
    public function getGenerators(): void
    {
        $autowire = new Autowire('test');
        $generator = \Mockery::mock(GeneratorInterface::class)->shouldIgnoreMissing();

        $config = new GRPCConfig([
            'generators' => ['foo', $autowire, $generator],
        ]);

        Assert::same($config->getGenerators(), ['foo', $autowire, $generator]);
    }
}
