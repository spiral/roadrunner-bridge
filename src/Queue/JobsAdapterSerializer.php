<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\SerializerRegistryInterface;
use Spiral\Serializer\SerializerInterface as SpiralSerializer;
use Spiral\RoadRunner\Jobs\Serializer\SerializerInterface;

/**
 * @internal
 */
final class JobsAdapterSerializer implements SerializerInterface
{
    private SpiralSerializer $serializer;

    public function __construct(
        private readonly SerializerRegistryInterface $registry
    ) {
        $this->serializer = $registry->getSerializer();
    }

    public function changeSerializer(string $jobType): self
    {
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
        return $this->serializer->unserialize($payload);
    }
}
