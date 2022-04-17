<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\Container\Autowire;
use Spiral\Core\FactoryInterface;
use Spiral\Core\InterceptableCore;
use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\RoadRunnerBridge\Tcp\Dispatcher;
use Spiral\RoadRunnerBridge\Tcp\Interceptor;
use Spiral\RoadRunnerBridge\Tcp\Server;
use Spiral\RoadRunnerBridge\Tcp\Service;
use Spiral\RoadRunnerBridge\Tcp\TcpServerHandler;

final class TcpBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
    ];

    protected const SINGLETONS = [
        Service\LocatorInterface::class => Service\ServiceLocator::class,
        Interceptor\LocatorInterface::class => Interceptor\InterceptorLocator::class,
        Server::class => [self::class, 'createServer'],
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
     * @param Autowire|Service\ServiceInterface|string $interceptor
     */
    public function addInterceptor($interceptor): void
    {
        $this->config->modify(TcpConfig::CONFIG, new Append('interceptors', null, $interceptor));
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

    private function createServer(
        TcpServerHandler $handler,
        Interceptor\LocatorInterface $locator,
        TcpConfig $config
    ): Server {
        $core = new InterceptableCore($handler);

        foreach ($locator->getInterceptors() as $interceptor) {
            $core->addInterceptor($interceptor);
        }

        return new Server($config, $core);
    }
}
