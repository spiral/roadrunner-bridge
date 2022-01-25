<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface as RRQueueInterface;

final class Queue implements QueueInterface
{
    use QueueTrait;

    private PipelineRegistryInterface $registry;
    private CreateInfoInterface $connector;
    private ?RRQueueInterface $queue = null;
    private bool $consume;

    public function __construct(
        PipelineRegistryInterface $registry,
        CreateInfoInterface $connector,
        bool $consume = true
    ) {
        $this->registry = $registry;
        $this->consume = $consume;
        $this->connector = $connector;
    }

    /**
     * Queue pipeline name
     */
    public function getName(): string
    {
        return $this->connector->getName();
    }

    /**
     * {@inheritDoc}
     * @throws JobsException
     */
    public function push(string $name, array $payload = [], OptionsInterface $options = null): string
    {
        $this->initQueue();

        $task = $this->queue->dispatch(
            $this->queue->create(
                $name,
                $payload,
                $options ? new Options($options->getDelay() ?? Options::DEFAULT_DELAY) : null
            )
        );

        return $task->getId();
    }

    private function initQueue(): void
    {
        if ($this->queue !== null) {
            return;
        }

        if (! $this->registry->isExists($this->connector->getName())) {
            $this->queue = $this->registry->create($this->connector, $this->consume);

            return;
        }

        $this->queue = $this->registry->connect($this->connector->getName());
    }
}
