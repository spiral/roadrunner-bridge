<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Config\TcpConfig;

final class ServiceLocator implements LocatorInterface
{
    private TcpConfig $config;
    private ContainerInterface $container;

    public function __construct(TcpConfig $config, ContainerInterface $container)
    {
        $this->config = $config;
        $this->container = $container;
    }

    public function getService(string $server): ServiceInterface
    {
        $service = $this->config->getService($server);

        switch (true) {
            case $service instanceof ServiceInterface:
                return $service;
            case $service instanceof Autowire:
                return $service->resolve($this->container->get(FactoryInterface::class));
            default:
                return $this->container->get($service);
        }
    }
}
