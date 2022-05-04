<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\InjectableConfig;

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

    /**
     * @return array<object>|array<string>
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
