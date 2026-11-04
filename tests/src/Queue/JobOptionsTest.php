<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\RoadRunnerBridge\Queue\JobOptions;
use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

#[Test]
final class JobOptionsTest
{
    public function factoriesAndWithersKeepTheClass(): void
    {
        $options = JobOptions::onQueue('mail')
            ->withDelay(5)
            ->withHeader('foo', 'bar')
            ->withAddedHeader('foo', 'baz')
            ->withHeader('drop', 'me')
            ->withoutHeader('drop')
            ->withQueue('mail')
            ->withPriority(3)
            ->withAutoAck(false);

        Assert::instanceOf($options, JobOptions::class);
        Assert::instanceOf(JobOptions::delayed(5), JobOptions::class);
        Assert::same($options->jsonSerialize(), [
            'delay' => 5,
            'queue' => 'mail',
            'headers' => ['foo' => ['bar', 'baz']],
            'priority' => 3,
            'auto_ack' => false,
        ]);
    }

    public function priorityAndAutoAckAreUnsetByDefault(): void
    {
        $options = JobOptions::onQueue('mail');

        Assert::null($options->getPriority());
        Assert::null($options->getAutoAck());
    }

    public function nullUnsetsPriorityAndAutoAck(): void
    {
        $options = JobOptions::onQueue('mail')->withPriority(3)->withAutoAck(true)
            ->withPriority(null)->withAutoAck(null);

        Assert::null($options->getPriority());
        Assert::null($options->getAutoAck());
    }

    public function withersDoNotChangeTheOriginal(): void
    {
        $options = JobOptions::onQueue('mail');
        $options->withPriority(3)->withAutoAck(true);

        Assert::null($options->getPriority());
        Assert::null($options->getAutoAck());
    }

    #[DataSet([0])]
    #[DataSet([-1])]
    public function nonPositivePriorityIsRejected(int $priority): void
    {
        Expect::exception(InvalidArgumentException::class);

        JobOptions::onQueue('mail')->withPriority($priority);
    }
}
