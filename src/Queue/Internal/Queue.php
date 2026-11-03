<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Internal;

use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueInterface;
use Spiral\Queue\QueueTrait;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use Spiral\RoadRunner\Jobs\QueueInterface as RRQueueInterface;
use Spiral\RoadRunner\Jobs\Task\PreparedTask;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;
use Spiral\RoadRunner\Jobs\Task\ProvidesHeadersInterface;
use Spiral\RoadRunnerBridge\Queue\Options as BridgeOptions;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;

/**
 * @psalm-import-type TPipeline from \Spiral\RoadRunnerBridge\Config\QueueConfig
 *
 * @internal
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
    ) {}

    /**
     * @param non-empty-string $name
     *
     * @return non-empty-string
     * @throws InvalidArgumentException
     * @throws JobsException
     *
     * @psalm-suppress MoreSpecificImplementedParamType RoadRunner rejects an empty job name
     */
    #[\Override]
    public function push(
        string $name,
        mixed $payload = '',
        OptionsInterface|JobsOptionsInterface|null $options = null,
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

        # The merge in create() takes the push headers in place of the pipeline's, and reads a zero delay
        # and a false auto-ack as unset.
        $defaults = $queue->getDefaultOptions();
        if ($defaults instanceof ProvidesHeadersInterface) {
            /** @var array<non-empty-string> $values */
            foreach ($defaults->getHeaders() as $header => $values) {
                if (!$preparedTask->hasHeader($header)) {
                    $preparedTask = $preparedTask->withHeader($header, $values);
                }
            }
        }

        if ($options instanceof OptionsInterface && ($delay = $options->getDelay()) !== null) {
            $preparedTask = $preparedTask->withDelay($delay);
        }

        if ($options instanceof BridgeOptions && $options->getAutoAck() === false && $preparedTask instanceof PreparedTask) {
            $preparedTask = $preparedTask->withAutoAck(false);
        }

        if (\is_object($payload)) {
            $preparedTask = $preparedTask->withHeader(
                self::SERIALIZED_CLASS_HEADER_KEY,
                $payload::class,
            );
        }

        return $preparedTask;
    }
}
