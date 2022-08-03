<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Queue\Driver\SyncDriver;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;
use Spiral\RoadRunner\Jobs\Task\QueuedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\Tests\TestCase;

class QueueManagerTest extends TestCase
{
    /** @var QueueConnectionProviderInterface */
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = m::mock(PipelineRegistryInterface::class);
        $this->getContainer()->bind(PipelineRegistryInterface::class, $this->registry);

        $this->manager = $this->getContainer()->get(QueueConnectionProviderInterface::class);
    }

    public function testGetsRoadRunnerQueue(): void
    {
        $this->assertInstanceOf(
            Queue::class,
            $this->manager->getConnection('roadrunner')
        );
    }

    public function testPushIntoDefaultRoadRunnerPipeline()
    {
        $this->registry->shouldReceive('getPipeline')
            ->once()
            ->with('memory', 'foo')
            ->andReturn($queue = m::mock(QueueInterface::class));

        $queuedTask = m::mock(QueuedTaskInterface::class);
        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id');

        $queue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $queue->shouldReceive('create')->once()->andReturn($preparedTask);

        $this->assertSame(
            'task-id',
            $this->manager->getConnection('roadrunner')->push('foo', ['boo' => 'bar'])
        );
    }

    public function testGetsSyncQueue(): void
    {
        $this->assertInstanceOf(
            SyncDriver::class,
            $this->manager->getConnection('sync')
        );
    }
}
