<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\RoadRunnerBridge\Queue\Options;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
final class OptionsTest
{
    public function factoriesAndWithersKeepTheClass(): void
    {
        $options = Options::onQueue('mail')
            ->withDelay(5)
            ->withHeader('foo', 'bar')
            ->withAddedHeader('foo', 'baz')
            ->withoutHeader('none')
            ->withPriority(3)
            ->withAutoAck(false);

        Assert::instanceOf($options, Options::class);
        Assert::instanceOf(Options::delayed(5), Options::class);
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
        $options = Options::onQueue('mail');

        Assert::null($options->getPriority());
        Assert::null($options->getAutoAck());
    }

    public function withersDoNotChangeTheOriginal(): void
    {
        $options = Options::onQueue('mail');
        $options->withPriority(3)->withAutoAck(true);

        Assert::null($options->getPriority());
        Assert::null($options->getAutoAck());
    }

    public function zeroPriorityIsRejected(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        Options::onQueue('mail')->withPriority(0);
    }
}
