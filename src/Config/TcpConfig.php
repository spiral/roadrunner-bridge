<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\Container\Autowire;
use Spiral\Core\InjectableConfig;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;
use Spiral\RoadRunnerBridge\Tcp\Interceptor;

/**
 * @psalm-import-type TInterceptor from Interceptor\RegistryInterface
 * @psalm-import-type TLegacyInterceptor from Interceptor\RegistryInterface
 */
final class TcpConfig extends InjectableConfig
{
    public const CONFIG = 'tcp';

    protected array $config = [
        'services' => [],
        'interceptors' => [],
    ];

    /**
     * @return array<non-empty-string, Autowire|class-string<ServiceInterface>|ServiceInterface>
     */
    public function getServices(): array
    {
        return $this->config['services'] ?? [];
    }

    /**
     * @return array<non-empty-string, list<TInterceptor|TLegacyInterceptor>>
     */
    public function getInterceptors(): array
    {
        return $this->config['interceptors'] ?? [];
    }

    public function isDebugMode(): bool
    {
        return (bool) $this->config['debug'];
    }
}
