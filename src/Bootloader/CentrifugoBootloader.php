<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\RPCCentrifugoApi;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Broadcast;
use Spiral\RoadRunnerBridge\Centrifugo\Dispatcher;
use Spiral\RoadRunnerBridge\Centrifugo\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Centrifugo\LogErrorHandler;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceRegistry;
use RoadRunner\Centrifugo\CentrifugoWorkerInterface;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Broadcasting\Bootloader\BroadcastingBootloader;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor;
use Spiral\RoadRunnerBridge\Config\CentrifugoConfig;

final class CentrifugoBootloader extends Bootloader
{
    protected const SINGLETONS = [
        RegistryInterface::class => [self::class, 'initServiceRegistry'],
        Interceptor\RegistryInterface::class => [self::class, 'initInterceptorRegistry'],
        CentrifugoWorkerInterface::class => CentrifugoWorker::class,
        ErrorHandlerInterface::class => LogErrorHandler::class,
        CentrifugoApiInterface::class => RPCCentrifugoApi::class,
    ];

    public function __construct(
        private readonly ConfiguratorInterface $config
    ) {
    }

    private function initConfig(): void
    {
        $this->config->setDefaults(
            CentrifugoConfig::CONFIG,
            [
                'services' => [],
                'interceptors' => [],
            ]
        );
    }

    public function init(
        BroadcastingBootloader $broadcasting,
    ): void {
        $this->initConfig();
        $broadcasting->registerDriverAlias(Broadcast::class, 'centrifugo');
    }

    public function boot(
        AbstractKernel $kernel,
        Dispatcher $dispatcher,
    ): void {
        $kernel->addDispatcher($dispatcher);
    }

    private function initInterceptorRegistry(
        CentrifugoConfig $config,
        ContainerInterface $container,
        FactoryInterface $factory
    ): Interceptor\RegistryInterface {
        return new Interceptor\InterceptorRegistry($config->getInterceptors(), $container, $factory);
    }

    private function initServiceRegistry(
        CentrifugoConfig $config,
        ContainerInterface $container,
        FactoryInterface $factory
    ) {
        return new ServiceRegistry($config->getServices(), $container, $factory);
    }
}
