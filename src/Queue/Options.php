<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\Options as QueueOptions;

/**
 * Spiral queue options with the RoadRunner job priority and auto-ack, so one push can name its pipeline too.
 *
 * Other queue drivers read it as plain Spiral options.
 *
 * @api
 */
final class Options extends QueueOptions
{
    /** @var positive-int|null */
    private ?int $priority = null;

    private ?bool $autoAck = null;

    # The parent factories create the parent class, and its `with*()` methods declare it as their return.
    #[\Override]
    public static function delayed(int $delay): self
    {
        return (new self())->withDelay($delay);
    }

    #[\Override]
    public static function onQueue(?string $queue): self
    {
        return (new self())->withQueue($queue);
    }

    #[\Override]
    public function withQueue(?string $queue): self
    {
        return self::narrow(parent::withQueue($queue));
    }

    #[\Override]
    public function withDelay(?int $delay): self
    {
        return self::narrow(parent::withDelay($delay));
    }

    #[\Override]
    public function withHeader(string $name, string|array $value): self
    {
        return self::narrow(parent::withHeader($name, $value));
    }

    #[\Override]
    public function withAddedHeader(string $name, string|array $value): self
    {
        return self::narrow(parent::withAddedHeader($name, $value));
    }

    #[\Override]
    public function withoutHeader(string $name): self
    {
        return self::narrow(parent::withoutHeader($name));
    }

    /**
     * RoadRunner hands out a lower value first. A job priority applies among the jobs RoadRunner holds:
     * a whole memory or BoltDB pipeline, but only the prefetched part of a broker queue.
     *
     * @param int|null $priority A positive value, or null for the pipeline priority. RoadRunner reads `0`
     *        as the pipeline priority too, so it is rejected.
     *
     * @throws \InvalidArgumentException
     */
    public function withPriority(?int $priority): self
    {
        $priority === null || $priority > 0 or throw new \InvalidArgumentException(
            \sprintf('Priority must be a positive integer, %d given.', $priority),
        );

        $options = clone $this;
        $options->priority = $priority;

        return $options;
    }

    /**
     * @return positive-int|null
     */
    public function getPriority(): ?int
    {
        return $this->priority;
    }

    /**
     * @param bool|null $autoAck True acks the job when a worker takes it, before the handler runs.
     *        Null takes the pipeline default.
     */
    public function withAutoAck(?bool $autoAck): self
    {
        $options = clone $this;
        $options->autoAck = $autoAck;

        return $options;
    }

    public function getAutoAck(): ?bool
    {
        return $this->autoAck;
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return parent::jsonSerialize() + [
            'priority' => $this->priority,
            'auto_ack' => $this->autoAck,
        ];
    }

    private static function narrow(QueueOptions $options): self
    {
        \assert($options instanceof self);
        return $options;
    }
}
