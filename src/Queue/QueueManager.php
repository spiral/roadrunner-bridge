<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Core\Exception\Container\ContainerException;
use Spiral\Core\FactoryInterface;
use Spiral\Queue\QueueInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;

final class QueueManager
{
    /** @var QueueInterface[] */
    private array $connections = [];
    private QueueConfig $config;
    private FactoryInterface $factory;

    public function __construct(QueueConfig $config, FactoryInterface $factory)
    {
        $this->config = $config;
        $this->factory = $factory;
    }

    /**
     * @throws Exception\InvalidArgumentException
     */
    public function getConnection(?string $name = null): QueueInterface
    {
        $name = $name ?: $this->getDefaultDriver();
        // Replaces alias with real connection name
        $name = $this->config->getAliases()[$name] ?? $name;

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolveConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * @throws Exception\InvalidArgumentException
     * @throws Exception\NotSupportedDriverException
     */
    private function resolveConnection(string $name): QueueInterface
    {
        $config = $this->config->getConnection($name);

        try {
            return $this->factory->make($config['driver'], $config);
        } catch (ContainerException $e) {
            throw new Exception\NotSupportedDriverException(
                sprintf(
                    'Driver `%s` is not supported. Connection `%s` cannot be created.',
                    $config['driver'],
                    $name
                ),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * @throws Exception\InvalidArgumentException
     */
    private function getDefaultDriver(): string
    {
        return $this->config->getDefaultDriver();
    }
}
