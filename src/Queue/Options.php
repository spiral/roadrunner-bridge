<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\Options as QueueOptions;
use Spiral\RoadRunner\Jobs\Task\WritableHeadersInterface;
use Spiral\RoadRunner\Jobs\Task\WritableHeadersTrait;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;

final class Options extends QueueOptions implements WritableHeadersInterface, OptionsInterface
{
    use WritableHeadersTrait;

    /**
     * @var positive-int|0
     */
    private int $priority = JobsOptionsInterface::DEFAULT_PRIORITY;

    private bool $autoAck = JobsOptionsInterface::DEFAULT_AUTO_ACK;

    public function withPriority(int $priority): self
    {
        $options = clone $this;
        $options->priority = $priority;

        return $options;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function autoAck(bool $autoAck = true): self
    {
        $options = clone $this;
        $options->autoAck = $autoAck;

        return $options;
    }

    public function isAutoAck(): bool
    {
        return $this->autoAck;
    }
}
