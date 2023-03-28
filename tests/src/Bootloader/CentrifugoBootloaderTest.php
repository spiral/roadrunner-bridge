<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use RoadRunner\Centrifugo\CentrifugoApiInterface;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\CentrifugoWorkerInterface;
use RoadRunner\Centrifugo\RPCCentrifugoApi;
use Spiral\Broadcasting\Config\BroadcastConfig;
use Spiral\Core\ConfigsInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Broadcast;
use Spiral\RoadRunnerBridge\Centrifugo\Dispatcher;
use Spiral\RoadRunnerBridge\Centrifugo\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor;
use Spiral\RoadRunnerBridge\Centrifugo\LogErrorHandler;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceRegistry;
use Spiral\RoadRunnerBridge\Config\CentrifugoConfig;
use Spiral\Tests\TestCase;

final class CentrifugoBootloaderTest extends TestCase
{
    public function testRegistryShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            RegistryInterface::class,
            ServiceRegistry::class
        );
    }

    public function testInterceptorRegistryShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            Interceptor\RegistryInterface::class,
            Interceptor\InterceptorRegistry::class
        );
    }

    public function testCentrifugoWorkerShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            CentrifugoWorkerInterface::class,
            CentrifugoWorker::class
        );

        // TODO fix problem with rr worker
        ob_end_flush();
    }

    public function testErrorHandlerShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            ErrorHandlerInterface::class,
            LogErrorHandler::class
        );
    }

    public function testCentrifugoApiShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            CentrifugoApiInterface::class,
            RPCCentrifugoApi::class
        );
    }

    public function testDispatcherShouldBeRegistered(): void
    {
        $this->assertDispatcherRegistered(Dispatcher::class);
    }

    public function testBroadcastingDriverShouldBeRegistered(): void
    {
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig(BroadcastConfig::CONFIG);

        $this->assertArrayHasKey('centrifugo', $config['driverAliases']);
        $this->assertSame(Broadcast::class, $config['driverAliases']['centrifugo']);
    }

    public function testDefaultConfigShouldBeDefined(): void
    {
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig(CentrifugoConfig::CONFIG);

        $this->assertIsArray($config);
        $this->assertSame([
            'services' => [],
            'interceptors' => [],
        ], $config);
    }
}
