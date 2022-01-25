<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;

/**
 * @internal
 */
final class RPCPipelineRegistry implements PipelineRegistryInterface
{
    private JobsInterface $jobs;
    private int $expiresAt = 0;
    private array $existsPipelines = [];
    private int $ttl;

    public function __construct(JobsInterface $jobs, int $ttl = 60)
    {
        $this->jobs = $jobs;
        $this->ttl = $ttl;
    }

    /**
     * Check if RoadRunner jobs pipeline exists
     */
    public function isExists(string $pipeline): bool
    {
        if ($this->expiresAt < time()) {
            $this->existsPipelines = array_keys(
                iterator_to_array($this->jobs->getIterator())
            );
            $this->expiresAt = time() + $this->ttl;
        }

        return in_array($pipeline, $this->existsPipelines);
    }

    /**
     * Create a new RoadRunner jobs pipeline
     */
    public function create(CreateInfoInterface $pipelineInfo, bool $shouldBeConsumed = true): QueueInterface
    {
        $this->expiresAt = 0;
        $queue = $this->jobs->create($pipelineInfo);
        if ($shouldBeConsumed) {
            $queue->resume();
        }

        return $queue;
    }

    /**
     * Create to the RoadRunner jobs pipeline
     */
    public function connect(string $pipeline): QueueInterface
    {
        return $this->jobs->connect($pipeline);
    }
}
