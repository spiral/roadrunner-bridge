<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

use Spiral\Core\Container\Autowire;

interface RegistryInterface
{
    /**
     * @psalm-param non-empty-string $server
     */
    public function getService(string $server): ServiceInterface;

    /**
     * @psalm-param non-empty-string $server
     * @param Autowire|ServiceInterface|string $service
     */
    public function register(string $server, $service): void;

    /**
     * @psalm-param non-empty-string $server
     */
    public function hasService(string $server): bool;
}
