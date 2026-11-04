<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\Queue\Options;

/**
 * Spiral queue options with the RoadRunner job priority and auto-ack, so one push can name its pipeline too.
 *
 * Other queue drivers read it as plain Spiral options.
 *
 * @api
 */
final class JobOptions extends Options
{
    /** @var positive-int|null */
    private ?int $priority = null;

    private ?bool $autoAck = null;

    # The parent factories create the parent class, and its `with*()` methods declare it as their return.
    # `static` rather than `self` keeps the overrides valid if the parent moves to `static`.
    #[\Override]
    public static function delayed(int $delay): static
    {
        return (new self())->withDelay($delay);
    }

    #[\Override]
    public static function onQueue(?string $queue): static
    {
        return (new self())->withQueue($queue);
    }

    #[\Override]
    public function withQueue(?string $queue): static
    {
        return $this->narrow(parent::withQueue($queue));
    }

    #[\Override]
    public function withDelay(?int $delay): static
    {
        return $this->narrow(parent::withDelay($delay));
    }

    #[\Override]
    public function withHeader(string $name, string|array $value): static
    {
        return $this->narrow(parent::withHeader($name, $value));
    }

    #[\Override]
    public function withAddedHeader(string $name, string|array $value): static
    {
        return $this->narrow(parent::withAddedHeader($name, $value));
    }

    #[\Override]
    public function withoutHeader(string $name): static
    {
        return $this->narrow(parent::withoutHeader($name));
    }

    /**
     * RoadRunner hands out jobs with a lower value first. All pipelines share one RoadRunner priority
     * queue, but each pipeline puts there only the jobs it has prefetched.
     *
     * @param int|null $priority A positive value, or null for the pipeline priority. RoadRunner reads `0`
     *        as the pipeline priority too, so it is rejected.
     *
     * @throws InvalidArgumentException
     */
    public function withPriority(?int $priority): static
    {
        $priority === null || $priority > 0 or throw new InvalidArgumentException(
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
     * @param bool|null $autoAck True acks the job before any worker runs it (a broker driver acks on fetch),
     *        so a failure or a RoadRunner restart loses it. Null takes the pipeline default.
     */
    public function withAutoAck(?bool $autoAck): static
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

    private function narrow(Options $options): static
    {
        \assert($options instanceof self);
        return $options;
    }
}
