<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Queue\RPCPipelineRegistry;
use Spiral\Tests\TestCase;

final class RPCPipelineRegistryTest extends TestCase
{
    /** @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|JobsInterface */
    private $jobs;
    private RPCPipelineRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new RPCPipelineRegistry(
            $this->jobs = m::mock(JobsInterface::class),
            60
        );
    }

    public function testExistsMethod()
    {
        $this->jobs->shouldReceive('getIterator')->once()->andReturnUsing(function () {
            yield 'foo' => 'queue1';
        });

        $this->assertTrue($this->registry->isExists('foo'));
        $this->assertFalse($this->registry->isExists('bar'));
    }

    public function testCreatesPipelineAndStartConsumingFromGivenConfig()
    {
        $info = m::mock(CreateInfoInterface::class);

        $this->jobs->shouldReceive('create')
            ->once()
            ->with($info)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $queue->shouldReceive('resume')->once();

        $this->assertSame($queue, $this->registry->create($info, true));
    }

    public function testCreatesPipelineWithoutStartingConsumingFromGivenConfig()
    {
        $info = m::mock(CreateInfoInterface::class);

        $this->jobs->shouldReceive('create')
            ->once()
            ->with($info)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertSame($queue, $this->registry->create($info, false));
    }

    public function testConnectsToAPipeline()
    {
        $this->jobs->shouldReceive('connect')
            ->once()
            ->with('foo')
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertSame($queue, $this->registry->connect('foo'));
    }
}
