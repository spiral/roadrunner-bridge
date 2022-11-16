<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\InjectableConfig;
use Spiral\RoadRunner\GRPC\ServiceInterface;

final class GRPCConfig extends InjectableConfig
{
    public const CONFIG = 'grpc';

    protected array $config = [
        'binaryPath' => null,
        'generatedPath' => null,
        'namespace' => null,
        'servicesBasePath' => null,
        'services' => [],
        'interceptors' => [],
    ];

    public function getBinaryPath(): ?string
    {
        return $this->config['binaryPath'] ?? null;
    }

    /**
     * Path, where generated DTO files put.
     */
    public function getGeneratedPath(): ?string
    {
        return $this->config['generatedPath'] ?? null;
    }

    /**
     * Get proto file namespace
     */
    public function getNamespace(): ?string
    {
        return $this->config['namespace'] ?? null;
    }

    /**
     * Get proto files base path
     */
    public function getServicesBasePath(): ?string
    {
        return $this->config['servicesBasePath'] ?? null;
    }

    /**
     * @return array<class-string<ServiceInterface>>
     */
    public function getServices(): array
    {
        return (array)($this->config['services'] ?? []);
    }

    /**
     * @return array<Autowire|class-string<CoreInterceptorInterface>>
     */
    public function getInterceptors(): array
    {
        return (array)($this->config['interceptors'] ?? []);
    }
}
