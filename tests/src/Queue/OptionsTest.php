<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\Jobs\OptionsInterface;
use Spiral\RoadRunnerBridge\Queue\Options;

final class OptionsTest extends TestCase
{
    public function testPriority(): void
    {
        $options = new Options();

        $this->assertSame(OptionsInterface::DEFAULT_PRIORITY, $options->getPriority());
        $this->assertSame(5, $options->withPriority(5)->getPriority());
        $this->assertSame(3, $options->withPriority(3)->getPriority());
        $this->assertSame(0, $options->withPriority(0)->getPriority());
    }

    public function testAutoAck(): void
    {
        $options = new Options();

        $this->assertSame(OptionsInterface::DEFAULT_AUTO_ACK, $options->isAutoAck());
        $this->assertSame(true, $options->autoAck()->isAutoAck());
        $this->assertSame(false, $options->autoAck(false)->isAutoAck());
    }
}
