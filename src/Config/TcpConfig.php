<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\InjectableConfig;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

final class TcpConfig extends InjectableConfig
{
    public const CONFIG = 'tcp';

    /**
     * @return array<class-string<ServiceInterface>>|array<object>
     */
    public function getServices(): array
    {
        return (array) ($this->config['services'] ?? []);
    }

    /**
     * @return array<class-string<CoreInterceptorInterface>>|array<object>
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
