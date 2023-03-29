<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Queue\OptionsInterface;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;

/**
 * @psalm-type TPipeline = array{
 *     connector: CreateInfoInterface,
 *     consume: bool,
 *     options: OptionsInterface|JobsOptionsInterface|null
 * }
 */
final class QueueConfig
{
    public function __construct(
        private readonly array $pipelines
    ) {
    }

    /**
     * @return array<non-empty-string, TPipeline> $pipelines
     */
    public function getPipelines(): array
    {
        return $this->pipelines;
    }
}
