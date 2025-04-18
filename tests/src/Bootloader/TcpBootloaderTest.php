<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Core\ConfigsInterface;
use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\RoadRunnerBridge\Tcp\Interceptor;
use Spiral\RoadRunnerBridge\Tcp\Internal\Dispatcher;
use Spiral\RoadRunnerBridge\Tcp\Internal\Server;
use Spiral\RoadRunnerBridge\Tcp\Service;
use Spiral\Tests\TestCase;

final class TcpBootloaderTest extends TestCase
{
    public function testLocatorShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            Interceptor\RegistryInterface::class,
            \Spiral\RoadRunnerBridge\Tcp\Internal\InterceptorRegistry::class,
        );

        $this->assertContainerBoundAsSingleton(
            Service\RegistryInterface::class,
            \Spiral\RoadRunnerBridge\Tcp\Internal\ServiceRegistry::class,
        );
    }

    public function testServerShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            Server::class,
            Server::class,
        );
    }

    public function testDispatcherShouldBeRegistered(): void
    {
        $this->assertDispatcherRegistered(Dispatcher::class);
    }

    public function testConfigShouldBeDefined(): void
    {
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig(TcpConfig::CONFIG);

        $this->assertIsArray($config);
        $this->assertSame([
            'services' => [],
            'interceptors' => [],
            'debug' => false,
        ], $config);
    }
}
