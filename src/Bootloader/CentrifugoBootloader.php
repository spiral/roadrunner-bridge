<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\CentrifugoWorkerInterface;
use RoadRunner\Centrifugo\RPCCentrifugoApi;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Broadcasting\Bootloader\BroadcastingBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Broadcast;
use Spiral\RoadRunnerBridge\Centrifugo\Dispatcher;
use Spiral\RoadRunnerBridge\Centrifugo\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor;
use Spiral\RoadRunnerBridge\Centrifugo\LogErrorHandler;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceRegistry;
use Spiral\RoadRunnerBridge\Config\CentrifugoConfig;

final class CentrifugoBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {
    }

    public function defineSingletons(): array
    {
        return [
            RegistryInterface::class => [self::class, 'initServiceRegistry'],
            Interceptor\RegistryInterface::class => [self::class, 'initInterceptorRegistry'],
            CentrifugoWorkerInterface::class => CentrifugoWorker::class,
            ErrorHandlerInterface::class => LogErrorHandler::class,
            CentrifugoApiInterface::class => RPCCentrifugoApi::class,
        ];
    }

    private function initConfig(): void
    {
        $this->config->setDefaults(
            CentrifugoConfig::CONFIG,
            [
                'services' => [],
                'interceptors' => [],
            ],
        );
    }

    public function init(BroadcastingBootloader $broadcasting): void
    {
        $this->initConfig();
        $broadcasting->registerDriverAlias(Broadcast::class, 'centrifugo');
    }

    public function boot(AbstractKernel $kernel): void
    {
        $kernel->addDispatcher(Dispatcher::class);
    }

    private function initInterceptorRegistry(
        CentrifugoConfig $config,
        ContainerInterface $container,
        FactoryInterface $factory,
    ): Interceptor\RegistryInterface {
        return new Interceptor\InterceptorRegistry($config->getInterceptors(), $container, $factory);
    }

    private function initServiceRegistry(
        CentrifugoConfig $config,
        #[Proxy] ContainerInterface $container,
        #[Proxy] FactoryInterface $factory,
    ): RegistryInterface {
        return new ServiceRegistry($config->getServices(), $container, $factory);
    }
}
