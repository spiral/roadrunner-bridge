<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Config\ConfigManager;
use Spiral\Config\LoaderInterface;
use Spiral\Core\ConfigsInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\BootloaderGenerator;
use Spiral\RoadRunnerBridge\GRPC\Generator\ConfigGenerator;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistry;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\ServiceClientGenerator;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\Invoker;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunnerBridge\Bootloader\GRPCBootloader;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\RoadRunnerBridge\GRPC\ServiceLocator;
use Spiral\Tests\TestCase;
use Mockery as m;

final class GRPCBootloaderTest extends TestCase
{
    public function testGetsServerAsASingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            Server::class,
            Server::class
        );
    }

    public function testGetsInvoker(): void
    {
        $this->assertContainerBoundAsSingleton(
            InvokerInterface::class,
            Invoker::class
        );
    }

    public function testGetsServiceLocator(): void
    {
        $this->assertContainerBoundAsSingleton(
            LocatorInterface::class,
            ServiceLocator::class
        );
    }

    public function testGetsProtoFilesRepository(): void
    {
        $this->assertContainerBoundAsSingleton(
            ProtoFilesRepositoryInterface::class,
            FileRepository::class
        );
    }

    public function testDispatcherShouldBeRegistered(): void
    {
        $this->assertDispatcherRegistered(Dispatcher::class);
    }

    public function testOdlBootloaderShouldNotBeRegistered(): void
    {
        $this->assertBootloaderMissed('Spiral\Bootloader\GRPC');
    }

    public function testGeneratorRegistryShouldBeBoundAsSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            GeneratorRegistryInterface::class,
            GeneratorRegistry::class
        );
    }

    public function testConfigShouldBeDefined(): void
    {
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig('grpc');

        $this->assertSame([
            'binaryPath' => $this->getDirectoryByAlias('app') . '../protoc-gen-php-grpc',
            'generatedPath' => null,
            'namespace' => null,
            'servicesBasePath' => $this->getDirectoryByAlias('app') . 'proto',
            'services' => [
                $this->getDirectoryByAlias('app') . 'proto/echo.proto',
                $this->getDirectoryByAlias('app') . 'proto/foo.proto',
            ],
            'interceptors' => [],
            'generators' => [
                ServiceClientGenerator::class,
                ConfigGenerator::class,
                BootloaderGenerator::class
            ]
        ], $config);
    }

    public function testAddInterceptor(): void
    {
        $configs = new ConfigManager($this->createMock(LoaderInterface::class));
        $configs->setDefaults(GRPCConfig::CONFIG, ['interceptors' => []]);

        $interceptor = m::mock(CoreInterceptorInterface::class);
        $autowire = new Autowire('test');

        $bootloader = new GRPCBootloader($configs);
        $bootloader->addInterceptor('foo');
        $bootloader->addInterceptor($interceptor);
        $bootloader->addInterceptor($autowire);

        $this->assertSame([
            'foo', $interceptor, $autowire,
        ], $configs->getConfig(GRPCConfig::CONFIG)['interceptors']);
    }

    public function testAddGenerator(): void
    {
        $configs = new ConfigManager($this->createMock(LoaderInterface::class));
        $configs->setDefaults(GRPCConfig::CONFIG, ['generators' => []]);

        $generator = m::mock(GeneratorInterface::class);
        $autowire = new Autowire('test');

        $bootloader = new GRPCBootloader($configs);
        $bootloader->addGenerator('foo');
        $bootloader->addGenerator($generator);
        $bootloader->addGenerator($autowire);

        $this->assertSame([
            'foo', $generator, $autowire,
        ], $configs->getConfig(GRPCConfig::CONFIG)['generators']);
    }
}
