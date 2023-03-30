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
     * Declare all pipelines from the config that should be consumed by the application.
     */
    public function declareConsumerPipelines(): void;

    /**
     * Get pipeline with given name
     *
     * If pipeline not exists in the RoadRunner, it will be created
     *
     * @param non-empty-string $name
     */
    public function getPipeline(string $name): QueueInterface;
}
