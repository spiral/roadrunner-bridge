<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

use Spiral\Core\Container\Autowire;

/**
 * @psalm-type TService = Autowire|ServiceInterface|class-string<ServiceInterface>
 */
interface RegistryInterface
{
    /**
     * @param non-empty-string $server
     */
    public function getService(string $server): ServiceInterface;

    /**
     * @param non-empty-string $server
     * @param TService $service
     */
    public function register(string $server, Autowire|ServiceInterface|string $service): void;

    /**
     * @param non-empty-string $server
     */
    public function hasService(string $server): bool;
}
