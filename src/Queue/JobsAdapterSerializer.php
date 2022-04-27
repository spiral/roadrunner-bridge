<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\RoadRunner\Jobs\Serializer\SerializerInterface;
use Spiral\Queue\SerializerInterface as QueueSerializerInterface;

/**
 * @internal
 */
final class JobsAdapterSerializer implements SerializerInterface
{
    public function __construct(
        private readonly QueueSerializerInterface $serializer
    ) {
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
