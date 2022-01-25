<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\SerializerInterface;
use Spiral\RoadRunner\Jobs\Exception\SerializationException;

/**
 * @internal
 */
final class DefaultSerializer implements SerializerInterface
{
    /**
     * {@inheritDoc}
     */
    public function serialize(array $payload): string
    {
        try {
            return \Opis\Closure\serialize($payload);
        } catch (\Throwable $e) {
            throw new SerializationException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize(string $payload): array
    {
        try {
            return (array)\Opis\Closure\unserialize($payload);
        } catch (\Throwable $e) {
            throw new SerializationException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}
