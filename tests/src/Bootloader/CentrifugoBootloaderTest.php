<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use RoadRunner\Centrifugo\CentrifugoApiInterface;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\CentrifugoWorkerInterface;
use RoadRunner\Centrifugo\RPCCentrifugoApi;
use Spiral\Broadcasting\Config\BroadcastConfig;
use Spiral\Core\ConfigsInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\Broadcast;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\Dispatcher;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\LogErrorHandler;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\ServiceRegistry;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Config\CentrifugoConfig;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class CentrifugoBootloaderTest extends TestCase
{
    #[Test]
    public function registryShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            RegistryInterface::class,
            ServiceRegistry::class,
        );
    }

    #[Test]
    public function interceptorRegistryShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            Interceptor\RegistryInterface::class,
            \Spiral\RoadRunnerBridge\Centrifugo\Internal\InterceptorRegistry::class,
        );
    }

    #[Test]
    public function centrifugoWorkerShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            CentrifugoWorkerInterface::class,
            CentrifugoWorker::class,
        );

        // TODO fix problem with rr worker
        \ob_end_flush();
    }

    #[Test]
    public function errorHandlerShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            ErrorHandlerInterface::class,
            LogErrorHandler::class,
        );
    }

    #[Test]
    public function centrifugoApiShouldBeSingleton(): void
    {
        $this->assertContainerBoundAsSingleton(
            CentrifugoApiInterface::class,
            RPCCentrifugoApi::class,
        );
    }

    #[Test]
    public function dispatcherShouldBeRegistered(): void
    {
        $this->assertDispatcherRegistered(Dispatcher::class);
    }

    #[Test]
    public function broadcastingDriverShouldBeRegistered(): void
    {
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig(BroadcastConfig::CONFIG);

        Assert::array($config['driverAliases'])->hasKeys('centrifugo');
        Assert::same($config['driverAliases']['centrifugo'], Broadcast::class);
    }

    #[Test]
    public function defaultConfigShouldBeDefined(): void
    {
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig(CentrifugoConfig::CONFIG);

        Assert::array($config);
        Assert::same($config, [
            'services' => [],
            'interceptors' => [],
        ]);
    }
}
