<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\SerializerRegistryInterface;
use Spiral\Queue\SerializerInterface as QueueSerializerInterface;
use Spiral\RoadRunner\Jobs\Serializer\SerializerInterface;

/**
 * @internal
 */
final class JobsAdapterSerializer implements SerializerInterface
{
    private ?SerializerRegistryInterface $registry = null;
    private QueueSerializerInterface $serializer;

    public function __construct(QueueSerializerInterface $serializer)
    {
        if ($serializer instanceof SerializerRegistryInterface) {
            $this->registry = $serializer;
        }

        $this->serializer = $serializer;
    }

    public function changeSerializer(string $jobType): self
    {
        if ($this->registry === null) {
            return $this;
        }

        $serializer = clone $this;
        $serializer->serializer = $this->registry->getSerializer($jobType);

        return $serializer;
    }

    public function serialize(array $payload): string
    {
        return $this->serializer->serialize($payload);
    }

    public function deserialize(string $payload): array
    {
        return $this->serializer->deserialize($payload);
    }
}
