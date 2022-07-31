<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\QueueRegistry;
use Spiral\RoadRunner\Jobs\Serializer\SerializerInterface;
use Spiral\Serializer\SerializerManager;

/**
 * @internal
 */
final class JobsAdapterSerializer implements SerializerInterface
{
    public function __construct(
        private readonly SerializerManager $manager,
        private readonly HandlerRegistryInterface $handlerRegistry,
        private ?string $format = null
    ) {
    }

    public function withJobType(string $jobType): self
    {
        if ($this->handlerRegistry instanceof QueueRegistry && $this->handlerRegistry->hasSerializer($jobType)) {
            $serializer = clone $this;
            $serializer->format = $this->handlerRegistry->getSerializerFormat($jobType);

            return $serializer;
        }

        return $this;
    }

    public function withFormat(string $format = null): self
    {
        if ($format === null) {
            return $this;
        }

        $serializer = clone $this;
        $serializer->format = $format;

        return $serializer;
    }

    public function serialize(array $payload): string
    {
        return (string) $this->manager->getSerializer($this->format)->serialize($payload);
    }

    public function deserialize(string $payload): array
    {
        return $this->manager->getSerializer($this->format)->unserialize($payload);
    }
}
