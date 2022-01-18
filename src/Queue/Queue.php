<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\QueueInterface as RRQueueInterface;

class Queue implements QueueInterface
{
    use QueueTrait;

    private RRQueueInterface $queue;
    private JobsInterface $jobs;
    private HandlerRegistryInterface $handlerRegistry;

    public function __construct(
        HandlerRegistryInterface $handlerRegistry,
        JobsInterface $jobs,
        RRQueueInterface $queue
    ) {
        $this->queue = $queue;
        $this->jobs = $jobs;
        $this->handlerRegistry = $handlerRegistry;
    }

    /**
     * {@inheritDoc}
     * @throws JobsException
     */
    public function push(string $name, array $payload = [], OptionsInterface $options = null): string
    {
        $context = $this->getContext($options);

        /** @var \Spiral\RoadRunner\Jobs\QueueInterface $queue */
        $queue = $context->queue;

        $task = $queue->dispatch(
            $queue->create(
                $name,
                $payload,
                $options ? new Options($options->getDelay() ?? Options::DEFAULT_DELAY) : null
            )
        );

        return $task->getId();
    }

    /**
     * @param OptionsInterface|null $options
     * @return QueueInterface
     */
    private function getContext(?OptionsInterface $options): QueueInterface
    {
        if ($options instanceof OptionsInterface && $options->getPipeline() !== null) {
            $original = $this->jobs->connect($options->getPipeline());

            return new self($this->handlerRegistry, $this->jobs, $original);
        }

        return $this;
    }

}
