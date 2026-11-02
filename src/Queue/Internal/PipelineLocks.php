<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Internal;

use Spiral\RoadRunnerBridge\Config\QueueConfig;

/**
 * Names of the RoadRunner locks that coordinate pipeline declaration between processes.
 *
 * @internal
 */
final readonly class PipelineLocks
{
    /** @var non-empty-string */
    private string $prefix;

    public function __construct(QueueConfig $config)
    {
        $this->prefix = $config->getLockPrefix();
    }

    /**
     * Held while one process declares the pipeline.
     *
     * @return non-empty-string
     */
    public function declare(string $pipeline): string
    {
        return $this->prefix . 'declare:' . $pipeline;
    }

    /**
     * Held from the declaration of a `consume` pipeline until it is resumed.
     *
     * @return non-empty-string
     */
    public function pending(string $pipeline): string
    {
        return $this->prefix . 'pending:' . $pipeline;
    }
}
