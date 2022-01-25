<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;

interface PipelineRegistryInterface
{
    /**
     * Check if RoadRunner jobs pipeline exists
     */
    public function isExists(string $pipeline): bool;

    /**
     * Create a new RoadRunner jobs pipeline
     */
    public function create(CreateInfoInterface $pipelineInfo, bool $shouldBeConsumed = true): QueueInterface;

    /**
     * Create to the RoadRunner jobs pipeline
     */
    public function connect(string $pipeline): QueueInterface;
}
