<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\Tests\ConsoleTestCase;
use Testo\Assert;
use Testo\Test;

final class ResumeCommandTest extends ConsoleTestCase
{
    #[Test]
    public function resumePipeline(): void
    {
        $registry = $this->mockContainer(PipelineRegistryInterface::class);

        $registry->shouldReceive('getPipeline')->once()->with('foo')->andReturn(
            $queue = m::mock(QueueInterface::class),
        );

        $queue->shouldReceive('isPaused')->once()->andReturnTrue();
        $queue->shouldReceive('resume')->once();

        $result = $this->runCommand('rr:jobs:consume', ['pipeline' => 'foo']);
        Assert::string($result)->contains('Pipeline [foo] has been started consuming tasks.');
    }

    #[Test]
    public function resumeIsConsumingPipeline(): void
    {
        $registry = $this->mockContainer(PipelineRegistryInterface::class);

        $registry->shouldReceive('getPipeline')->once()->with('foo')->andReturn(
            $queue = m::mock(QueueInterface::class),
        );

        $queue->shouldReceive('isPaused')->once()->andReturnFalse();

        $result = $this->runCommand('rr:jobs:consume', ['pipeline' => 'foo']);
        Assert::string($result)->contains('Pipeline [foo] is not paused.');
    }
}
