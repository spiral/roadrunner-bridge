<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Core\FactoryInterface;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\Queue\OptionsInterface as QueueOptionsInterface;
use Spiral\Queue\QueueInterface;
use Spiral\Queue\QueueTrait;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface as RRQueueInterface;
use Spiral\RoadRunner\Jobs\Task\ProvidesHeadersInterface;

final class Queue implements QueueInterface
{
    use QueueTrait;
    /** @var array<non-empty-string, RRQueueInterface> */
    private array $queues = [];

    /**
     * @param array<non-empty-string, array{connector: CreateInfoInterface, consume: bool}> $pipelines
     * @param array<non-empty-string,non-empty-string> $aliases
     * @param non-empty-string|null $default
     */
    public function __construct(
        private readonly FactoryInterface $factory,
        private readonly array $pipelines = [],
        private readonly array $aliases = [],
        private readonly ?string $default = null
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws JobsException
     * @throws InvalidArgumentException
     */
    public function push(
        string $name,
        array $payload = [],
        QueueOptionsInterface|ProvidesHeadersInterface|OptionsInterface $options = null
    ): string {
        $queue = $this->initQueue($name, $options ? $options->getQueue() ?? $this->default : $this->default);

        $preparedTask = $queue->create(
            $name,
            $payload,
            $options ? new Options($options->getDelay() ?? Options::DEFAULT_DELAY) : null
        );

        if ($options instanceof ProvidesHeadersInterface) {
            foreach ($options->getHeaders() as $header => $values) {
                $preparedTask = $preparedTask->withHeader($header, $values);
            }
        }

        return $queue->dispatch($preparedTask)->getId();
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
