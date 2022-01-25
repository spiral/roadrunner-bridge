<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Core\ConfigsInterface;
use Spiral\RoadRunner\GRPC\Invoker;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\RoadRunnerBridge\GRPC\ServiceLocator;
use Spiral\Tests\TestCase;

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

    public function testDispatcherShouldBeRegistered()
    {
        $dispatchers = $this->accessProtected($this->app, 'dispatchers');

        $this->assertCount(1, array_filter($dispatchers, function ($dispatcher) {
            return $dispatcher instanceof Dispatcher;
        }));
    }

    public function testConfigShouldBeDefined()
    {
        $configurator = $this->container->get(ConfigsInterface::class);
        $config = $configurator->getConfig('grpc');

        $this->assertSame([
            'binaryPath' => null,
            'services' => [],
        ], $config);
    }
}
