<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\InjectableConfig;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;

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
        'generators' => [],
    ];

    public function getBinaryPath(): ?string
    {
        return $this->config['binaryPath'] ?? null;
    }

    /**
     * Path, where generated DTO files should be stored.
     * @return non-empty-string|null
     */
    public function getGeneratedPath(): ?string
    {
        return $this->config['generatedPath'] ?? null;
    }

    /**
     * Base namespace for generated proto files.
     */
    public function getNamespace(): ?string
    {
        return $this->config['namespace'] ?? null;
    }

    /**
     * Root path for all proto files in which imports will be searched.
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

    /**
     * @return array<Autowire|class-string<GeneratorInterface>|GeneratorInterface>
     */
    public function getGenerators(): array
    {
        return (array)($this->config['generators'] ?? []);
    }
}
