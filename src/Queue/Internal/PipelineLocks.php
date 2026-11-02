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
    /**
     * How long a declaration may hold its lock, in seconds. Kept well below the default 60s `allocate_timeout`
     * of a RoadRunner pool, since a booting jobs worker may wait for the lock.
     */
    public const TTL = 20;

    /** A booting jobs worker outwaits the TTL, so it takes over the lock of a declarer that died. */
    public const BOOT_WAIT = 25;

    /** A push waits shorter, failing rather than holding its request. */
    public const PUSH_WAIT = 10;

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
