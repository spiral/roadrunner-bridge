<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use RoadRunner\Centrifugo\RequestType;
use Spiral\Core\InjectableConfig;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Interceptor;

/**
 * @psalm-import-type TService from RegistryInterface
 * @psalm-import-type TInterceptor from Interceptor\RegistryInterface
 */
final class CentrifugoConfig extends InjectableConfig
{
    public const CONFIG = 'centrifugo';

    protected array $config = [
        'services' => [],
        'interceptors' => [],
    ];

    /**
     * @return array<RequestType, TService>
     */
    public function getServices(): array
    {
        return $this->config['services'] ?? [];
    }

    /**
     * @return array<RequestType, TInterceptor[]>
     */
    public function getInterceptors(): array
    {
        return $this->config['interceptors'] ?? [];
    }
}
