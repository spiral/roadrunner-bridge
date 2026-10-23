<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\InjectableConfig;
use Spiral\Grpc\Client\Config\GrpcClientConfig;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;

final class GRPCConfig extends InjectableConfig
{
    public const CONFIG = 'grpc';

    protected array $config = [
        'bufPath' => null,
        'protocPath' => null,
        'pluginPath' => null,
        'bufTemplate' => null,
        'generatedPath' => null,
        'namespace' => null,
        'servicesBasePath' => null,
        'services' => [],
        'interceptors' => [],
        'generators' => [],
        'client' => [],
    ];

    /**
     * @psalm-capabilities read-props
     */
    public function getClientConfig(): GrpcClientConfig
    {
        // Map Client options
        return match (true) {
            !isset($this->config['client']) => new GrpcClientConfig(),
            \is_array($this->config['client']) => new GrpcClientConfig(
                interceptors: $this->config['client']['interceptors'] ?? [],
                services: $this->config['client']['services'] ?? [],
            ),
            default => $this->config['client'],
        };
    }

    /**
     * Path to the buf binary.
     */
    public function getBufPath(): ?string
    {
        return $this->config['bufPath'] ?? null;
    }

    /**
     * Path to the protoc binary that buf runs for the built-in PHP generator.
     */
    public function getProtocPath(): ?string
    {
        return $this->config['protocPath'] ?? null;
    }

    /**
     * Path to the protoc-gen-php-grpc plugin binary.
     */
    public function getPluginPath(): ?string
    {
        return $this->config['pluginPath'] ?? null;
    }

    /**
     * Path to a user-owned buf.gen.yaml template.
     *
     * The template is used as is: its plugins and output directories replace the built-in ones,
     * so `generatedPath` and `namespace` do not relocate the generated files.
     */
    public function getBufTemplate(): ?string
    {
        return $this->config['bufTemplate'] ?? null;
    }

    /**
     * Path, where generated DTO files should be stored.
     *
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
     * Root directory of the proto files: the buf input that all imports are resolved from.
     */
    public function getServicesBasePath(): ?string
    {
        return $this->config['servicesBasePath'] ?? null;
    }

    /**
     * @return array<class-string<ServiceInterface>>
     *
     * @psalm-capabilities read-props
     */
    public function getServices(): array
    {
        return (array) ($this->config['services'] ?? []);
    }

    /**
     * @return array<Autowire|class-string<InterceptorInterface>|class-string<CoreInterceptorInterface>>
     */
    public function getInterceptors(): array
    {
        return (array) ($this->config['interceptors'] ?? []);
    }

    /**
     * @return array<Autowire|class-string<GeneratorInterface>|GeneratorInterface>
     */
    public function getGenerators(): array
    {
        return (array) ($this->config['generators'] ?? []);
    }
}
