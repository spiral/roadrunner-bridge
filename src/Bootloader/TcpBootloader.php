<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\RoadRunnerBridge\Tcp\Dispatcher;
use Spiral\RoadRunnerBridge\Tcp\Interceptor;
use Spiral\RoadRunnerBridge\Tcp\Server;
use Spiral\RoadRunnerBridge\Tcp\Service;

final class TcpBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {
    }

    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            Service\RegistryInterface::class => [self::class, 'initServiceRegistry'],
            Interceptor\RegistryInterface::class => [self::class, 'initInterceptorRegistry'],
            Server::class => Server::class,
        ];
    }

    public function init(EnvironmentInterface $environment): void
    {
        $this->initTcpConfig($environment);
    }

    public function boot(KernelInterface $kernel): void
    {
        $kernel->addDispatcher(Dispatcher::class);
    }

    private function initTcpConfig(EnvironmentInterface $environment): void
    {
        $this->config->setDefaults(
            TcpConfig::CONFIG,
            [
                'services' => [],
                'interceptors' => [],
                'debug' => $environment->get('TCP_DEBUG', false),
            ],
        );
    }

    private function initInterceptorRegistry(
        TcpConfig $config,
        ContainerInterface $container,
    ): Interceptor\RegistryInterface {
        return new Interceptor\InterceptorRegistry($config->getInterceptors(), $container);
    }

    private function initServiceRegistry(
        TcpConfig $config,
        ContainerInterface $container,
    ): Service\RegistryInterface {
        return new Service\ServiceRegistry($config->getServices(), $container);
    }
}
