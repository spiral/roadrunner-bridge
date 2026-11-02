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
 *
 * @api
 */
final readonly class QueueConfig
{
    public const DEFAULT_LOCK_PREFIX = 'roadrunner-bridge:jobs:';

    /**
     * @param string $lockPrefix Prefix of the RoadRunner locks that guard pipeline declaration.
     */
    public function __construct(
        private array $pipelines,
        private string $lockPrefix = self::DEFAULT_LOCK_PREFIX,
    ) {
        $lockPrefix !== '' or throw new \InvalidArgumentException('The queue `lockPrefix` must not be empty.');
    }

    /**
     * @return array<non-empty-string, TPipeline> $pipelines
     */
    public function getPipelines(): array
    {
        return $this->pipelines;
    }

    /**
     * @return non-empty-string
     */
    public function getLockPrefix(): string
    {
        \assert($this->lockPrefix !== '');

        return $this->lockPrefix;
    }
}
