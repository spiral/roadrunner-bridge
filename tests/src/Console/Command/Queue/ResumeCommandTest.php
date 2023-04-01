<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Queue;

use Mockery as m;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\Tests\ConsoleTestCase;

final class ResumeCommandTest extends ConsoleTestCase
{
    public function testResumePipeline()
    {
        $registry = $this->mockContainer(PipelineRegistryInterface::class);

        $registry->shouldReceive('getPipeline')->once()->with('foo')->andReturn(
            $queue = m::mock(QueueInterface::class),
        );

        $queue->shouldReceive('isPaused')->once()->andReturnTrue();
        $queue->shouldReceive('resume')->once();

        $result = $this->runCommand('rr:jobs:consume', ['pipeline' => 'foo']);
        $this->assertStringContainsString('Pipeline [foo] has been started consuming tasks.', $result);
    }

    public function testResumeIsConsumingPipeline()
    {
        $registry = $this->mockContainer(PipelineRegistryInterface::class);

        $registry->shouldReceive('getPipeline')->once()->with('foo')->andReturn(
            $queue = m::mock(QueueInterface::class),
        );

        $queue->shouldReceive('isPaused')->once()->andReturnFalse();

        $result = $this->runCommand('rr:jobs:consume', ['pipeline' => 'foo']);
        $this->assertStringContainsString('Pipeline [foo] is not paused.', $result);
    }
}
