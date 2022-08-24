<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Core\FactoryInterface;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;
use Spiral\Queue\QueueTrait;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface as RRQueueInterface;

final class Queue implements QueueInterface
{
    use QueueTrait;

    private FactoryInterface $factory;
    private int $ttl;
    /** @var non-empty-string|null */
    private ?string $default;
    /** @var array<non-empty-string, RRQueueInterface> */
    private array $queues = [];
    /** @var array<non-empty-string, array{connector: CreateInfoInterface, consume: bool}> */
    private array $pipelines;
    /** @var array<non-empty-string,non-empty-string> */
    private array $aliases;

    public function __construct(
        FactoryInterface $factory,
        array $pipelines = [],
        array $aliases = [],
        ?string $default = null
    ) {
        $this->default = $default;
        $this->factory = $factory;
        $this->pipelines = $pipelines;
        $this->aliases = $aliases;
    }

    /**
     * {@inheritDoc}
     *
     * @throws JobsException
     * @throws InvalidArgumentException
     */
    public function push(string $name, array $payload = [], OptionsInterface $options = null): string
    {
        $queue = $this->initQueue($name, $options ? $options->getQueue() ?? $this->default : $this->default);

        $task = $queue->dispatch(
            $queue->create(
                $name,
                $payload,
                $options ? new Options($options->getDelay() ?? Options::DEFAULT_DELAY) : null
            )
        );

        return $task->getId();
    }

    private function initQueue(string $jobType, ?string $pipeline): RRQueueInterface
    {
        if (! $pipeline) {
            throw new InvalidArgumentException('You must define default RoadRunner queue pipeline.');
        }

        if (isset($this->queues[$pipeline])) {
            return $this->queues[$pipeline];
        }

        $registry = $this->factory->make(PipelineRegistryInterface::class, [
            'pipelines' => $this->pipelines,
            'aliases' => $this->aliases,
        ]);

        return $this->queues[$pipeline] = $registry->getPipeline($pipeline, $jobType);
    }
}
