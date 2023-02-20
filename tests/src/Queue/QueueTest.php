<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Core\FactoryInterface;
use Spiral\Queue\Job\ObjectJob;
use Spiral\Queue\Options as QueueOptions;
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
    private m\LegacyMockInterface|FactoryInterface|m\MockInterface $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = m::mock(FactoryInterface::class);

        $pipelines = [
            'memory' => [
                'connector' => m::mock(CreateInfoInterface::class),
                'cunsume' => true,
            ],
        ];

        $aliases = [
            'user-data' => 'memory',
        ];

        $this->queue = new Queue($this->factory, $pipelines, $aliases, 'default');
    }

    public function testTaskShouldBePushedToDefaultQueue(): void
    {
        $this->factory->shouldReceive('make')
            ->once()
            ->withSomeOfArgs(PipelineRegistryInterface::class)
            ->andReturn($registry = m::mock(PipelineRegistryInterface::class));

        $registry->shouldReceive('getPipeline')
            ->once()
            ->with('default', 'foo')
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

    public function testTaskShouldBePushedToCustomQueue(): void
    {
        $this->factory->shouldReceive('make')
            ->twice()
            ->withSomeOfArgs(PipelineRegistryInterface::class)
            ->andReturn($registry = m::mock(PipelineRegistryInterface::class));

        $registry->shouldReceive('getPipeline')
            ->once()
            ->with('foo', 'foo')
            ->andReturn($fooQueue = m::mock(QueueInterface::class));

        $registry->shouldReceive('getPipeline')
            ->once()
            ->with('bar', 'bar')
            ->andReturn($barQueue = m::mock(QueueInterface::class));

        $queuedTask = m::mock(QueuedTaskInterface::class);
        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id1');
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id2');

        $fooQueue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $fooQueue->shouldReceive('create')->once()->withSomeOfArgs('foo')->andReturn($preparedTask);

        $barQueue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $barQueue->shouldReceive('create')->once()->withSomeOfArgs('bar')->andReturn($preparedTask);

        $this->assertSame('task-id1', $this->queue->push('foo', ['foo' => 'bar'], QueueOptions::onQueue('foo')));
        $this->assertSame('task-id2', $this->queue->push('bar', ['foo' => 'bar'], QueueOptions::onQueue('bar')));
    }

    public function testPushObject(): void
    {
        $this->factory->shouldReceive('make')
            ->once()
            ->withSomeOfArgs(PipelineRegistryInterface::class)
            ->andReturn($registry = m::mock(PipelineRegistryInterface::class));

        $registry->shouldReceive('getPipeline')
            ->once()
            ->with('default', ObjectJob::class)
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
}
