<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTask;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\Serializer\Exception\UnserializeException;

final class Consumer implements ConsumerInterface
{
    private WorkerInterface $worker;

    public function __construct(
        private readonly JobsAdapterSerializer $serializer,
        WorkerInterface $worker = null,
        private readonly array $pipelines = [],
    ) {
        $this->worker = $worker ?? Worker::create();
    }

    public function waitTask(): ?ReceivedTaskInterface
    {
        $payload = $this->worker->waitPayload();

        if ($payload === null) {
            return null;
        }

        $header = $this->serializer->withFormat('json')->deserialize($payload->header);

        return new ReceivedTask(
            $this->worker,
            $header['id'],
            $header['pipeline'],
            $header['job'],
            $this->getPayload($payload, $header['pipeline'],  $header['job']),
            (array) $header['headers']
        );
    }

    /**
     * @throws UnserializeException
     */
    private function getPayload(Payload $payload, string $pipeline, string $jobType): array
    {
        return $this->serializer
            ->withFormat($this->pipelines[$pipeline]['serializerFormat'] ?? null)
            ->withJobType($jobType)
            ->deserialize($payload->body);
    }
}
