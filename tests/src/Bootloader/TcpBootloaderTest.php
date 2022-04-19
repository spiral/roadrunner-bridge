<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\App\Tcp\TestInterceptor;
use Spiral\App\Tcp\TestService;
use Spiral\Core\ConfigsInterface;
use Spiral\Core\Container\Autowire;
use Spiral\RoadRunnerBridge\Bootloader\TcpBootloader;
use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\RoadRunnerBridge\Tcp\Dispatcher;
use Spiral\RoadRunnerBridge\Tcp\Interceptor;
use Spiral\RoadRunnerBridge\Tcp\Server;
use Spiral\RoadRunnerBridge\Tcp\Service;
use Spiral\Tests\TestCase;

final class TcpBootloaderTest extends TestCase
{
    public function testLocatorShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            Interceptor\LocatorInterface::class,
            Interceptor\InterceptorLocator::class
        );

        $this->assertContainerBoundAsSingleton(
            Service\LocatorInterface::class,
            Service\ServiceLocator::class
        );
    }

    public function testServerShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            Server::class,
            Server::class
        );
    }

    public function testDispatcherShouldBeRegistered()
    {
        $this->assertDispatcherLoaded(Dispatcher::class);
    }

    public function testConfigShouldBeDefined(): void
    {
        $configurator = $this->container->get(ConfigsInterface::class);
        $config = $configurator->getConfig(TcpConfig::CONFIG);

        $this->assertIsArray($config);
        $this->assertSame([
            'services' => [],
            'interceptors' => [],
            'debug' => false,
        ], $config);
    }

    public function testAddService(): void
    {
        $this->container->get(TcpBootloader::class)->addService('test', TestService::class);

        $configurator = $this->container->get(ConfigsInterface::class);
        $config = $configurator->getConfig(TcpConfig::CONFIG);

        $this->assertSame(['test' => TestService::class], $config['services']);
    }

    public function testAddOneInterceptor(): void
    {
        $this->container->get(TcpBootloader::class)->addInterceptors('server', new Autowire(TestInterceptor::class));

        $configurator = $this->container->get(ConfigsInterface::class);
        $config = $configurator->getConfig(TcpConfig::CONFIG);

        $this->assertInstanceOf(Autowire::class, $config['interceptors']['server'][0]);
    }

    public function testAddInterceptors(): void
    {
        $this->container->get(TcpBootloader::class)->addInterceptors('server', ['foo', 'bar']);

        $configurator = $this->container->get(ConfigsInterface::class);
        $config = $configurator->getConfig(TcpConfig::CONFIG);

        $this->assertSame(['server' => ['foo', 'bar']], $config['interceptors']);
    }
}
