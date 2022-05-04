<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\RoadRunnerBridge\Tcp\Dispatcher;
use Spiral\RoadRunnerBridge\Tcp\Interceptor;
use Spiral\RoadRunnerBridge\Tcp\Server;
use Spiral\RoadRunnerBridge\Tcp\Service;

final class TcpBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
    ];

    protected const SINGLETONS = [
        Service\RegistryInterface::class => [self::class, 'initServiceRegistry'],
        Interceptor\RegistryInterface::class => [self::class, 'initInterceptorRegistry'],
        Server::class => Server::class,
    ];

    private ConfiguratorInterface $config;

    public function __construct(ConfiguratorInterface $config)
    {
        $this->config = $config;
    }

    public function boot(EnvironmentInterface $environment): void
    {
        $this->initTcpConfig($environment);
    }

    public function start(KernelInterface $kernel, FactoryInterface $factory)
    {
        $kernel->addDispatcher($factory->make(Dispatcher::class));
    }

    private function initTcpConfig(EnvironmentInterface $environment): void
    {
        $this->config->setDefaults(
            TcpConfig::CONFIG,
            [
                'services' => [],
                'interceptors' => [],
                'debug' => $environment->get('TCP_DEBUG', false),
            ]
        );
    }

    private function initInterceptorRegistry(
        TcpConfig $config,
        ContainerInterface $container
    ): Interceptor\RegistryInterface {
        return new Interceptor\InterceptorRegistry($config->getInterceptors(), $container);
    }

    private function initServiceRegistry(
        TcpConfig $config,
        ContainerInterface $container
    ): Service\RegistryInterface {
        return new Service\ServiceRegistry($config->getServices(), $container);
    }
}
