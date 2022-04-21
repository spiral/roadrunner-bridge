<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

use Spiral\Core\Container\Autowire;

interface RegistryInterface
{
    public function getService(string $server): ServiceInterface;

    /**
     * @param Autowire|ServiceInterface|string $service
     */
    public function register(string $server, $service): void;

    public function hasService(string $server): bool;
}
