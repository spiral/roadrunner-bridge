<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Core\Container;
use Spiral\Queue\Job\CallableJob;
use Spiral\Queue\Job\ObjectJob;
use Spiral\Queue\Options;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;
use Spiral\RoadRunner\Jobs\Task\QueuedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\Tests\TestCase;

final class QueueTest extends TestCase
{
    private Queue $queue;
    /** @var m\LegacyMockInterface|m\MockInterface|PipelineRegistryInterface */
    private $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();

        $this->registry = m::mock(PipelineRegistryInterface::class);
        $container->bind(PipelineRegistryInterface::class, $this->registry);

        $pipelines = [
            'memory' => [
                'connector' => m::mock(CreateInfoInterface::class),
                'cunsume' => true
            ]
        ];

        $aliases = [
            'user-data' => 'memory'
        ];

        $this->queue = new Queue($container, $pipelines, $aliases, 'default');
    }

    public function testTaskShouldBePushedToDefaultQueue()
    {
        $this->registry->shouldReceive('getPipeline')
            ->once()
            ->with('default')
            ->andReturn($queue = m::mock(QueueInterface::class));

        $queuedTask = m::mock(QueuedTaskInterface::class);
        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id1');
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id2');

        $queue->shouldReceive('dispatch')->twice()->with($preparedTask)->andReturn($queuedTask);
        $queue->shouldReceive('create')->twice()->andReturn($preparedTask);

        $this->assertSame('task-id1', $this->queue->push('foo', ['foo' => 'bar']));
        $this->assertSame('task-id2', $this->queue->push('foo', ['foo' => 'bar']));
    }

    public function testTaskShouldBePushedToCustomQueue()
    {
        $this->registry->shouldReceive('getPipeline')
            ->once()
            ->with('foo')
            ->andReturn($fooQueue = m::mock(QueueInterface::class));

        $this->registry->shouldReceive('getPipeline')
            ->once()
            ->with('bar')
            ->andReturn($barQueue = m::mock(QueueInterface::class));

        $queuedTask = m::mock(QueuedTaskInterface::class);
        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id1');
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id2');

        $fooQueue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $fooQueue->shouldReceive('create')->once()->withSomeOfArgs('foo')->andReturn($preparedTask);

        $barQueue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $barQueue->shouldReceive('create')->once()->withSomeOfArgs('bar')->andReturn($preparedTask);

        $this->assertSame('task-id1', $this->queue->push('foo', ['foo' => 'bar'], Options::onQueue('foo')));
        $this->assertSame('task-id2', $this->queue->push('bar', ['foo' => 'bar'], Options::onQueue('bar')));
    }

    public function testPushObject()
    {
        $this->registry->shouldReceive('getPipeline')
            ->once()
            ->with('default')
            ->andReturn($queue = m::mock(QueueInterface::class));

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
        $this->registry->shouldReceive('getPipeline')
            ->once()
            ->with('default')
            ->andReturn($queue = m::mock(QueueInterface::class));

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
}
