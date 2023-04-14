<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Core\FactoryInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\Exception\NotFoundException;

/**
 * @psalm-import-type TService from RegistryInterface
 */
final class ServiceRegistry implements RegistryInterface
{
    /**
     * @var array<non-empty-string, TService>
     */
    private array $services = [];

    public function __construct(
        array $services,
        private readonly ContainerInterface $container
    ) {
        foreach ($services as $server => $service) {
            $this->register($server, $service);
        }
    }

    /**
     * @param non-empty-string $server
     * @param TService $service
     */
    final public function register(string $server, Autowire|ServiceInterface|string $service): void
    {
        $this->services[$server] = $service;
    }

    /**
     * @param non-empty-string $server
     */
    public function getService(string $server): ServiceInterface
    {
        if (!$this->hasService($server)) {
            throw new NotFoundException($server);
        }

        $service = match (true) {
            $this->services[$server] instanceof ServiceInterface => $this->services[$server],
            $this->services[$server] instanceof Autowire => $this->services[$server]->resolve(
                $this->container->get(FactoryInterface::class)
            ),
            default => $this->container->get($this->services[$server]),
        };

        \assert($service instanceof ServiceInterface);

        return $service;
    }

    /**
     * @param non-empty-string $server
     */
    public function hasService(string $server): bool
    {
        return isset($this->services[$server]);
    }
}
