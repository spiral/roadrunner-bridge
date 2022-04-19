<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\InjectableConfig;
use Spiral\RoadRunnerBridge\Config\Exception\Tcp\InvalidInterceptorException;
use Spiral\RoadRunnerBridge\Config\Exception\Tcp\InvalidServiceException;
use Spiral\RoadRunnerBridge\Config\Exception\Tcp\ServiceNotFoundException;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

final class TcpConfig extends InjectableConfig
{
    public const CONFIG = 'tcp';

    /**
     * @return array<object>|array<string>
     */
    public function getServices(): array
    {
        return (array) ($this->config['services'] ?? []);
    }

    public function hasService(string $server): bool
    {
        return isset($this->config['services'][$server]);
    }

    /**
     * @psalm-param non-empty-string $server
     *
     * @return Autowire|ServiceInterface|string
     */
    public function getService(string $server)
    {
        if (!$this->hasService($server)) {
            throw new ServiceNotFoundException($server);
        }

        if (!$this->isValidService($this->config['services'][$server])) {
            throw new InvalidServiceException(\get_debug_type($this->config['services'][$server]));
        }

        return $this->config['services'][$server];
    }

    /**
     * @psalm-param non-empty-string $server
     *
     * @return array<object>|array<string>
     */
    public function getInterceptors(string $server): array
    {
        $interceptors = $this->config['interceptors'][$server] ?? [];
        if (!\is_array($interceptors)) {
            $interceptors = [$interceptors];
        }

        foreach ($interceptors as $interceptor) {
            if (!$this->isValidInterceptor($interceptor)) {
                throw new InvalidInterceptorException(\get_debug_type($interceptor));
            }
        }

        return $interceptors;
    }

    public function isDebugMode(): bool
    {
        return (bool) $this->config['debug'];
    }

    /**
     * @param mixed $service
     */
    private function isValidService($service): bool
    {
        return $service instanceof ServiceInterface || $service instanceof Autowire || \is_string($service);
    }

    /**
     * @param mixed $interceptor
     */
    private function isValidInterceptor($interceptor): bool
    {
        return
            $interceptor instanceof CoreInterceptorInterface ||
            $interceptor instanceof Autowire ||
            \is_string($interceptor);
    }
}
