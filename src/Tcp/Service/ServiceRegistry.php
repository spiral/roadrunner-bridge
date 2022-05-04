<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\Exception\NotFoundException;

final class ServiceRegistry implements RegistryInterface
{
    private array $services;

    public function __construct(
        array $services,
        private readonly ContainerInterface $container
    ) {
        foreach ($services as $server => $service) {
            $this->register($server, $service);
        }
    }

    /**
     * @psalm-param non-empty-string $server
     */
    public function register(string $server, Autowire|ServiceInterface|string $service): void
    {
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
}
