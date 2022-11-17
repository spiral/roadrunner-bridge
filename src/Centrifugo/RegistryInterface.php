<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\Core\Container\Autowire;

/**
 * @psalm-type TService = Autowire|ServiceInterface|class-string<ServiceInterface>
 */
interface RegistryInterface
{
    /**
     * Get service by request type.
     */
    public function getService(RequestType $requestType): ?ServiceInterface;

    /**
     * Register a service for a given request type.
     *
     * @param TService $service
     */
    public function register(RequestType $requestType, Autowire|ServiceInterface|string $service): void;

    /**
     * Check if service is registered for a given request type.
     */
    public function hasService(RequestType $requestType): bool;
}
