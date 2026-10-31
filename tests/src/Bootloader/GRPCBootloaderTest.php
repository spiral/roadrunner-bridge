<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Mockery as m;
use Spiral\Config\ConfigManager;
use Spiral\Config\LoaderInterface;
use Spiral\Core\ConfigsInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunnerBridge\Bootloader\GRPCBootloader;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\GeneratorRegistry;
use Spiral\RoadRunnerBridge\GRPC\Internal\Dispatcher;
use Spiral\RoadRunnerBridge\GRPC\Internal\Invoker;
use Spiral\RoadRunnerBridge\GRPC\Internal\ProtoRepository\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\Internal\ServiceLocator;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class GRPCBootloaderTest extends TestCase
{
    #[Test]
    public function getsServerAsASingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            Server::class,
            Server::class,
        );
    }

    #[Test]
    public function getsInvoker(): void
    {
        $this->assertContainerBoundAsSingleton(
            InvokerInterface::class,
            Invoker::class,
        );
    }

    #[Test]
    public function getsServiceLocator(): void
    {
        $this->assertContainerBoundAsSingleton(
            LocatorInterface::class,
            ServiceLocator::class,
        );
    }

    #[Test]
    public function getsProtoFilesRepository(): void
    {
        $this->assertContainerBoundAsSingleton(
            ProtoFilesRepositoryInterface::class,
            FileRepository::class,
        );
    }

    #[Test]
    public function dispatcherShouldBeRegistered(): void
    {
        $this->assertDispatcherRegistered(Dispatcher::class);
    }

    #[Test]
    public function odlBootloaderShouldNotBeRegistered(): void
    {
        $this->assertBootloaderMissed('Spiral\Bootloader\GRPC');
    }

    #[Test]
    public function generatorRegistryShouldBeBoundAsSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            GeneratorRegistryInterface::class,
            GeneratorRegistry::class,
        );
    }

    #[Test]
    public function configShouldBeDefined(): void
    {
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig('grpc');

        Assert::same($config, [
            'bufPath' => $this->binary('buf'),
            'protocPath' => $this->binary('protoc'),
            'pluginPath' => $this->binary('protoc-gen-php-grpc'),
            'bufTemplate' => null,
            'generatedPath' => null,
            'namespace' => null,
            'servicesBasePath' => $this->getDirectoryByAlias('app') . 'proto',
            'services' => [
                $this->getDirectoryByAlias('app') . 'proto/service.proto',
                $this->getDirectoryByAlias('app') . 'proto/foo.proto',
            ],
            'interceptors' => [],
            'generators' => [],
            'client' => [
                'interceptors' => [],
            ],
        ]);
    }

    #[Test]
    public function addInterceptor(): void
    {
        $configs = new ConfigManager(m::mock(LoaderInterface::class)->shouldIgnoreMissing());
        $configs->setDefaults(GRPCConfig::CONFIG, ['interceptors' => []]);

        $interceptor = m::mock(CoreInterceptorInterface::class);
        $autowire = new Autowire('test');

        $bootloader = new GRPCBootloader($configs);
        $bootloader->addInterceptor('foo');
        $bootloader->addInterceptor($interceptor);
        $bootloader->addInterceptor($autowire);

        Assert::same($configs->getConfig(GRPCConfig::CONFIG)['interceptors'], [
            'foo', $interceptor, $autowire,
        ]);
    }

    #[Test]
    public function addGenerator(): void
    {
        $configs = new ConfigManager(m::mock(LoaderInterface::class)->shouldIgnoreMissing());
        $configs->setDefaults(GRPCConfig::CONFIG, ['generators' => []]);

        $generator = m::mock(GeneratorInterface::class);
        $autowire = new Autowire('test');

        $bootloader = new GRPCBootloader($configs);
        $bootloader->addGenerator('foo');
        $bootloader->addGenerator($generator);
        $bootloader->addGenerator($autowire);

        Assert::same($configs->getConfig(GRPCConfig::CONFIG)['generators'], [
            'foo', $generator, $autowire,
        ]);
    }

    private function binary(string $name): string
    {
        return \dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $name . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    }
}
