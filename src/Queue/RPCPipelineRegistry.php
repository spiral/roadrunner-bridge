<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunner\Jobs\Serializer\SerializerAwareInterface;

/**
 * @internal
 */
final class RPCPipelineRegistry implements PipelineRegistryInterface
{
    private int $expiresAt = 0;
    private array $existPipelines = [];

    /**
     * @param array<non-empty-string, array{connector: CreateInfoInterface, consume: bool}> $pipelines
     * @param array<non-empty-string,non-empty-string> $aliases
     * @param int $ttl Time to cache existing RoadRunner pipelines
     */
    public function __construct(
        private JobsInterface $jobs,
        private readonly JobsAdapterSerializer $serializer,
        private readonly array $pipelines,
        private readonly array $aliases,
        private readonly int $ttl = 60
    ) {
    }

    public function getPipeline(string $name): QueueInterface
    {
        if (isset($this->aliases[$name])) {
            $name = $this->aliases[$name];
        }

        if (! isset($this->pipelines[$name])) {
            throw new InvalidArgumentException(
                \sprintf('Queue pipeline with given name `%s` is not found.', $name)
            );
        }

        if (! isset($this->pipelines[$name]['connector'])) {
            throw new InvalidArgumentException(
                \sprintf('You must specify connector for given pipeline `%s`.', $name)
            );
        }

        if (!$this->pipelines[$name]['connector'] instanceof CreateInfoInterface) {
            throw new InvalidArgumentException(
                \sprintf('Connector should implement %s interface.', CreateInfoInterface::class)
            );
        }

        if ($this->jobs instanceof SerializerAwareInterface && !empty($this->pipelines[$name]['serializerFormat'])) {
            $this->jobs = $this->jobs->withSerializer(
                $this->serializer->withFormat($this->pipelines[$name]['serializerFormat'])
            );
        }

        /** @var CreateInfoInterface $connector */
        $connector = $this->pipelines[$name]['connector'];
        $consume = (bool)($this->pipelines[$name]['consume'] ?? true);

        if (! $this->isExists($connector)) {
            $queue = $this->create($connector, $consume);
        } else {
            $queue = $this->connect($connector);
        }

        return $queue;
    }

    /**
     * Check if RoadRunner jobs pipeline exists
     */
    private function isExists(CreateInfoInterface $connector): bool
    {
        if ($this->expiresAt < \time()) {
            $this->existPipelines = \array_keys(
                \iterator_to_array($this->jobs->getIterator())
            );
            $this->expiresAt = \time() + $this->ttl;
        }

        return \in_array($connector->getName(), $this->existPipelines, true);
    }

    /**
     * Create a new RoadRunner jobs pipeline
     */
    private function create(CreateInfoInterface $connector, bool $shouldBeConsumed = true): QueueInterface
    {
        $this->expiresAt = 0;
        $queue = $this->jobs->create($connector);
        if ($shouldBeConsumed) {
            $queue->resume();
        }

        return $queue;
    }

    /**
     * Connect to the RoadRunner jobs pipeline
     */
    private function connect(CreateInfoInterface $connector): QueueInterface
    {
        return $this->jobs->connect($connector->getName());
    }
}
