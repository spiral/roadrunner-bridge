<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;
use Spiral\RoadRunner\Jobs\Task\QueuedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\Job\CallableJob;
use Spiral\RoadRunnerBridge\Queue\Job\ObjectJob;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\Tests\TestCase;

final class QueueTest extends TestCase
{
    private Queue $queue;
    /** @var m\LegacyMockInterface|m\MockInterface|HandlerRegistryInterface */
    private $handler;
    /** @var m\LegacyMockInterface|m\MockInterface|CreateInfoInterface */
    private $connector;
    /** @var m\LegacyMockInterface|m\MockInterface|PipelineRegistryInterface */
    private $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new Queue(
            $this->registry = m::mock(PipelineRegistryInterface::class),
            $this->connector = m::mock(CreateInfoInterface::class),
            true
        );
    }

    public function testGetsName(): void
    {
        $this->connector->shouldReceive('getName')->andReturn('foo-pipeline');

        $this->assertSame('foo-pipeline', $this->queue->getName());
    }

    public function testQueueShouldBeCreatedIfItNotExistsInRoadRunner()
    {
        $queue = $this->assertQueueInit(false);

        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask = m::mock(QueuedTaskInterface::class);
        $queue->shouldReceive('create')->once()->withSomeOfArgs('foo', ['foo' => 'bar'])->andReturn($preparedTask);
        $queue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id');

        $this->assertSame('task-id', $this->queue->push('foo', ['foo' => 'bar']));
    }

    public function testQueueShouldBeNotCreatedIfItExistsInRoadRunner()
    {
        $queue = $this->assertQueueInit();

        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask = m::mock(QueuedTaskInterface::class);
        $queue->shouldReceive('create')->once()->withSomeOfArgs('foo', ['foo' => 'bar'])->andReturn($preparedTask);
        $queue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id');

        $this->assertSame('task-id', $this->queue->push('foo', ['foo' => 'bar']));
    }

    public function testQueueShouldBeCreatedInitOnlyOnce()
    {
        $queue = $this->assertQueueInit();

        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask = m::mock(QueuedTaskInterface::class);
        $queue->shouldReceive('create')->andReturn($preparedTask);
        $queue->shouldReceive('dispatch')->andReturn($queuedTask);

        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id');
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id1');

        $this->assertSame('task-id', $this->queue->push('foo', ['foo' => 'bar']));
        $this->assertSame('task-id1', $this->queue->push('foo1', ['foo' => 'bar']));
    }

    public function testPushObject()
    {
        $queue = $this->assertQueueInit();

        $object = new \stdClass();
        $object->foo = 'bar';

        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask = m::mock(QueuedTaskInterface::class);
        $queue->shouldReceive('create')->once()
            ->withSomeOfArgs(ObjectJob::class, ['object' => $object])
            ->andReturn($preparedTask);
        $queue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id');

        $this->assertSame('task-id', $this->queue->pushObject($object));
    }

    public function testPushCallable()
    {
        $queue = $this->assertQueueInit();

        $callback = function () {
            return 'bar';
        };

        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask = m::mock(QueuedTaskInterface::class);
        $queue->shouldReceive('create')->once()
            ->withSomeOfArgs(CallableJob::class, ['callback' => $callback])
            ->andReturn($preparedTask);
        $queue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id');

        $this->assertSame('task-id', $this->queue->pushCallable($callback));
    }

    /**
     * @return m\LegacyMockInterface|m\MockInterface|QueueInterface
     */
    protected function assertQueueInit(bool $exists = true): QueueInterface
    {
        $this->connector->shouldReceive('getName')->andReturn('foo-pipeline');
        $this->registry->shouldReceive('isExists')->once()->with('foo-pipeline')->andReturn($exists);

        $queue = m::mock(QueueInterface::class);

        if ($exists) {
            $this->registry->shouldReceive('connect')
                ->once()
                ->with('foo-pipeline')
                ->andReturn($queue);

            return $queue;
        }

        $this->registry->shouldReceive('create')
            ->once()
            ->with($this->connector, true)
            ->andReturn($queue);

        return $queue;
    }
}
