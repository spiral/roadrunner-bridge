<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\InjectableConfig;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;

/**
 * @psalm-import-type TService from RegistryInterface
 * @psalm-import-type TInterceptor from Interceptor\RegistryInterface
 * @psalm-import-type TLegacyInterceptor from Interceptor\RegistryInterface
 *
 * @api
 */
final class CentrifugoConfig extends InjectableConfig
{
    public const CONFIG = 'centrifugo';

    protected array $config = [
        'services' => [],
        'interceptors' => [],
    ];

    /**
     * @return array<non-empty-string, TService>
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
}
