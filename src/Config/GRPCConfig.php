<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

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
    ];

    public function getBinaryPath(): ?string
    {
        return $this->config['binaryPath'] ?? null;
    }

    public function getGeneratedPath(): ?string
    {
        return $this->config['generatedPath'] ?? null;
    }

    public function getNamespace(): ?string
    {
        return $this->config['namespace'] ?? null;
    }

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
}
