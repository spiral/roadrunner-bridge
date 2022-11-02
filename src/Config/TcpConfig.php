<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\InjectableConfig;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

final class TcpConfig extends InjectableConfig
{
    public const CONFIG = 'tcp';

    protected array $config = [
        'services' => [],
        'interceptors' => [],
    ];

    /**
     * @return array<non-empty-string, class-string<ServiceInterface>|ServiceInterface|Autowire>
     */
    public function getServices(): array
    {
        return $this->config['services'] ?? [];
    }

    /**
     * @return array<non-empty-string, list<class-string<CoreInterceptorInterface>|CoreInterceptorInterface|Autowire>>
     */
    public function getInterceptors(): array
    {
        return $this->config['interceptors'] ?? [];
    }

    public function isDebugMode(): bool
    {
        return (bool)$this->config['debug'];
    }
}
