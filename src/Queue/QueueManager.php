<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Psr\Container\ContainerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\QueueInterface;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\Exception\NotSupportedDriverException;

final class QueueManager
{
    /**
     * @var QueueInterface[]
     */
    private array $connections = [];
    private QueueConfig $config;
    private ContainerInterface $container;

    public function __construct(QueueConfig $config, ContainerInterface $container)
    {
        $this->config = $config;
        $this->container = $container;
    }

    public function getConnection(?string $name = null): QueueInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolveConnection($name);
        }

        return $this->connections[$name];
    }

    private function getDefaultDriver(): string
    {
        return $this->config->getDefault();
    }

    private function resolveConnection(string $name): QueueInterface
    {
        $config = $this->config->getConnection($name);

        switch ($config['driver']) {
            case 'roadrunner':
                return $this->createRoadRunnerQueue($config);
            case 'sync':
                return $this->createSyncQueue($config);
        }

        throw new NotSupportedDriverException('Queue driver `'.$config['driver'].'` is not supported.');
    }

    private function createSyncQueue(array $config): QueueInterface
    {
        return $this->container->get(ShortCircuit::class);
    }

    private function createRoadRunnerQueue(array $config): QueueInterface
    {
        $jobs = $this->container->get(JobsInterface::class);

        return new Queue(
            $this->container->get(HandlerRegistryInterface::class),
            $jobs,
            $jobs->connect($config['connector']->getName())
        );
    }
}
