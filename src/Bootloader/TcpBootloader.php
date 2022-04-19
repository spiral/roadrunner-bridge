<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\Container\Autowire;
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
        Service\LocatorInterface::class => Service\ServiceLocator::class,
        Interceptor\LocatorInterface::class => Interceptor\InterceptorLocator::class,
        Server::class => Server::class,
    ];

    private ConfiguratorInterface $config;

    public function __construct(ConfiguratorInterface $config)
    {
        $this->config = $config;
    }

    public function boot(): void
    {
        $this->initTcpConfig();
    }

    public function start(KernelInterface $kernel, FactoryInterface $factory)
    {
        $kernel->addDispatcher($factory->make(Dispatcher::class));
    }

    /**
     * @param Autowire|Service\ServiceInterface|string $service
     */
    public function addService(string $server, $service): void
    {
        $this->config->modify(TcpConfig::CONFIG, new Append('services', $server, $service));
    }

    /**
     * @param array|Autowire|Service\ServiceInterface|string $interceptor
     */
    public function addInterceptors(string $server, $interceptors): void
    {
        $this->config->modify(
            TcpConfig::CONFIG,
            new Append('interceptors', $server, \is_array($interceptors) ? $interceptors : [$interceptors])
        );
    }

    private function initTcpConfig(): void
    {
        $this->config->setDefaults(
            TcpConfig::CONFIG,
            [
                'services' => [],
                'interceptors' => [],
                'debug' => false,
            ]
        );
    }
}
