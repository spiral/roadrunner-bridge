<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\Core\Container\Autowire;
use Spiral\Core\FactoryInterface;

/**
 * @psalm-import-type TService from RegistryInterface
 */
final class ServiceRegistry implements RegistryInterface
{
    /** @var array<non-empty-string, TService> */
    private array $services = [];

    /**
     * @param array<non-empty-string, TService> $services
     */
    public function __construct(
        array $services,
        private readonly ContainerInterface $container,
        private readonly FactoryInterface $factory,
    ) {
        foreach ($services as $type => $service) {
            $this->register(RequestType::from($type), $service);
        }
    }

    public function register(RequestType $requestType, Autowire|ServiceInterface|string $service): void
    {
        $this->services[$requestType->value] = $service;
    }

    public function getService(RequestType $requestType): ?ServiceInterface
    {
        if (!$this->hasService($requestType)) {
            return null;
        }

        if (!$this->services[$requestType->value] instanceof ServiceInterface) {
            $this->services[$requestType->value] = $this->createService($this->services[$requestType->value]);
        }

        return $this->services[$requestType->value];
    }

    public function hasService(RequestType $requestType): bool
    {
        return isset($this->services[$requestType->value]);
    }

    /**
     * @psalm-suppress LessSpecificReturnStatement
     */
    private function createService(Autowire|ServiceInterface|string $service): ServiceInterface
    {
        return match (true) {
            $service instanceof ServiceInterface => $service,
            $service instanceof Autowire => $service->resolve($this->factory),
            default => $this->container->get($service),
        };
    }
}
