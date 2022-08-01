<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTask;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\Serializer\Exception\SerializeException;
use Spiral\Serializer\Exception\UnserializeException;

final class Consumer implements ConsumerInterface
{
    private WorkerInterface $worker;

    public function __construct(
        private readonly JobsAdapterSerializer $serializer,
        WorkerInterface $worker = null
    ) {
        $this->worker = $worker ?? Worker::create();
    }

    public function waitTask(): ?ReceivedTaskInterface
    {
        $payload = $this->worker->waitPayload();

        if ($payload === null) {
            return null;
        }

        $header = $this->getHeader($payload);

        return new ReceivedTask(
            $this->worker,
            $header['id'],
            $header['pipeline'],
            $header['job'],
            $this->getPayload($payload, $header['job']),
            (array) $header['headers']
        );
    }

    /**
     * @throws UnserializeException
     */
    private function getPayload(Payload $payload, string $jobType): array
    {
        return $this->serializer->changeSerializer($jobType)->deserialize($payload->body);
    }

    private function getHeader(Payload $payload): array
    {
        try {
            return (array) \json_decode($payload->header, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SerializeException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}
