<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;
use Spiral\Queue\QueueTrait;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use Spiral\RoadRunner\Jobs\QueueInterface as RRQueueInterface;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;

/**
 * @psalm-import-type TPipeline from \Spiral\RoadRunnerBridge\Config\QueueConfig
 */
final class Queue implements QueueInterface
{
    use QueueTrait;

    public const SERIALIZED_CLASS_HEADER_KEY = 'payload_class';

    /** @var array<non-empty-string, RRQueueInterface> */
    private array $queues = [];

    /**
     * @param non-empty-string|null $default
     */
    public function __construct(
        private readonly SerializerRegistryInterface $serializer,
        private readonly PipelineRegistryInterface $registry,
        private readonly ?string $pipeline = null,
        private readonly ?string $default = null,
    ) {
    }

    /**
     * @param non-empty-string $name
     *
     * @throws InvalidArgumentException
     * @throws JobsException
     *
     * @return non-empty-string
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function push(
        string $name,
        mixed $payload = '',
        OptionsInterface|JobsOptionsInterface $options = null,
    ): string {
        $defaultPipeline = $this->pipeline ?? $this->default;

        $queue = $this->initQueue(
            $options instanceof OptionsInterface
                ? $options->getQueue() ?? $defaultPipeline
                : $defaultPipeline,
        );

        $preparedTask = $this->createTask($queue, $name, $payload, $options);

        return $queue->dispatch($preparedTask)->getId();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function initQueue(?string $pipeline): RRQueueInterface
    {
        if ($pipeline === null || $pipeline === '') {
            throw new InvalidArgumentException('You must define RoadRunner queue pipeline.');
        }

        if (isset($this->queues[$pipeline])) {
            return $this->queues[$pipeline];
        }

        return $this->queues[$pipeline] = $this->registry->getPipeline($pipeline);
    }

    /**
     * @param non-empty-string $name
     */
    private function createTask(
        RRQueueInterface $queue,
        string $name,
        mixed $payload,
        OptionsInterface|JobsOptionsInterface|null $options,
    ): PreparedTaskInterface {
        $preparedTask = $queue->create(
            $name,
            $this->serializer->getSerializer($name)->serialize($payload),
            OptionsFactory::create($options),
        );

        if (\is_object($payload)) {
            $preparedTask = $preparedTask->withHeader(
                self::SERIALIZED_CLASS_HEADER_KEY,
                $payload::class,
            );
        }

        return $preparedTask;
    }
}
