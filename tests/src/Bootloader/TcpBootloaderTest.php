<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Core\ConfigsInterface;
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
}
