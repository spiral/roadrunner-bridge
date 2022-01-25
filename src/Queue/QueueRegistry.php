<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Psr\Container\ContainerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\HandlerInterface;

final class QueueRegistry implements HandlerRegistryInterface
{
    private array $handlers = [];
    private array $pipelines = [];
    private ContainerInterface $container;
    private HandlerRegistryInterface $fallbackHandlers;

    public function __construct(
        ContainerInterface $container,
        HandlerRegistryInterface $handlers
    ) {
        $this->container = $container;
        $this->fallbackHandlers = $handlers;
    }

    /**
     * Associate specific job type with handler class or object
     * @param HandlerInterface|string $handler
     */
    public function setHandler(string $jobType, $handler): void
    {
        $this->handlers[$jobType] = $handler;
    }

    /**
     * Get handler object for given job type
     */
    public function getHandler(string $jobType): HandlerInterface
    {
        if (isset($this->handlers[$jobType])) {
            if ($this->handlers[$jobType] instanceof HandlerInterface) {
                return $this->handlers[$jobType];
            }

            return $this->container->get($this->handlers[$jobType]);
        }

        return $this->fallbackHandlers->getHandler($jobType);
    }

    /**
     * Associate specific job type with connection
     */
    public function setPipeline(string $jobType, string $pipeline): void
    {
        $this->pipelines[$jobType] = $pipeline;
    }

    /**
     * Get connection for specific job type
     */
    public function getPipeline(string $jobType): ?string
    {
        return $this->pipelines[$jobType] ?? null;
    }
}
