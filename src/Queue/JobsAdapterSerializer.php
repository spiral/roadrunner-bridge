<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\RoadRunner\Jobs\Serializer\SerializerInterface;

/**
 * @internal
 */
final class JobsAdapterSerializer implements SerializerInterface
{
    private \Spiral\Queue\SerializerInterface $serializer;

    public function __construct(\Spiral\Queue\SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
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
