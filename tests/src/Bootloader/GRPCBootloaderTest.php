<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Config\ConfigManager;
use Spiral\Config\LoaderInterface;
use Spiral\Core\ConfigsInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
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
    public function testGetsServerAsASingleton()
    {
        $this->assertContainerBoundAsSingleton(
            Server::class,
            Server::class
        );
    }

    public function testGetsInvoker()
    {
        $this->assertContainerBoundAsSingleton(
            InvokerInterface::class,
            Invoker::class
        );
    }

    public function testGetsServiceLocator()
    {
        $this->assertContainerBoundAsSingleton(
            LocatorInterface::class,
            ServiceLocator::class
        );
    }

    public function testGetsProtoFilesRepository()
    {
        $this->assertContainerBoundAsSingleton(
            ProtoFilesRepositoryInterface::class,
            FileRepository::class
        );
    }

    public function testDispatcherShouldBeRegistered()
    {
        $this->assertDispatcherRegistered(Dispatcher::class);
    }

    public function testOdlBootloaderShouldNotBeRegistered()
    {
        $this->assertBootloaderMissed('Spiral\Bootloader\GRPC');
    }

    public function testConfigShouldBeDefined()
    {
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig('grpc');

        $this->assertSame([
            'binaryPath' => $this->getDirectoryByAlias('app') . '../protoc-gen-php-grpc',
            'services' => [
                $this->getDirectoryByAlias('app') . 'proto/echo.proto',
                $this->getDirectoryByAlias('app') . 'proto/foo.proto',
            ],
            'interceptors' => [],
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
}
