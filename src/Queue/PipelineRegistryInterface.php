<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\RoadRunner\Jobs\QueueInterface;

/**
 * @internal
 */
interface PipelineRegistryInterface
{
    /**
     * Get pipeline with given name
     *
     * If pipeline not exists in the RoadRunner, it will be created
     */
    public function getPipeline(string $name, string $jobType): QueueInterface;
}
