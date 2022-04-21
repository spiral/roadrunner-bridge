<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\Exception\InvalidException;
use Spiral\RoadRunnerBridge\Tcp\Service\Exception\NotFoundException;

final class ServiceRegistry implements RegistryInterface
{
    private array $services;
    private ContainerInterface $container;

    public function __construct(array $services, ContainerInterface $container)
    {
        foreach ($services as $server => $service) {
            $this->register($server, $service);
        }

        $this->container = $container;
    }

    /**
     * @psalm-param non-empty-string $server
     * @param Autowire|ServiceInterface|string $service
     */
    public function register(string $server, $service): void
    {
        $this->validate($service);

        $this->services[$server] = $service;
    }

    /**
     * @psalm-param non-empty-string $server
     */
    public function getService(string $server): ServiceInterface
    {
        if (!$this->hasService($server)) {
            throw new NotFoundException($server);
        }

        switch (true) {
            case $this->services[$server] instanceof ServiceInterface:
                return $this->services[$server];
            case $this->services[$server] instanceof Autowire:
                return $this->services[$server]->resolve($this->container->get(FactoryInterface::class));
            default:
                return $this->container->get($this->services[$server]);
        }
    }

    /**
     * @psalm-param non-empty-string $server
     */
    public function hasService(string $server): bool
    {
        return isset($this->services[$server]);
    }

    /**
     * @param mixed $service
     */
    private function validate($service): void
    {
        if ($service instanceof ServiceInterface || $service instanceof Autowire || \is_string($service)) {
            return;
        }

        throw new InvalidException(\get_debug_type($service));
    }
}
