<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\InjectableConfig;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;

final class QueueConfig extends InjectableConfig
{
    public const CONFIG = 'queue';

    public function getDefault(): string
    {
        return $this->config['default'] ?? 'default';
    }

    /**
     * @return array<CreateInfoInterface>
     */
    public function getConnections(?string $driver = null): array
    {
        $connections = $this->config['connections'] ?? [];

        if ($driver === null) {
            return $connections;
        }

        return array_filter($connections, static function (array $connection) use ($driver) {
            return $connection['driver'] === $driver;
        });
    }

    public function getConnection(string $name): array
    {
        if (! isset($this->getConnections()[$name]['driver'])) {
            throw new \RuntimeException('Queue connection `'.$name.'` was not found.');
        }

        return $this->getConnections()[$name];
    }

    public function getRegistryHandlers(): array
    {
        return $this->config['registry']['handlers'] ?? [];
    }
}
