<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Queue\Options as QueueOptions;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;
use Spiral\RoadRunner\Jobs\Task\QueuedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\Internal\Queue;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\Serializer\SerializerInterface;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

final class QueueTest extends TestCase
{
    private Queue $queue;
    private PipelineRegistryInterface|m\MockInterface $registry;
    private SerializerRegistryInterface|m\MockInterface $serializerRegistry;

    #[Test]
    public function taskShouldBePushedToDefaultQueue(): void
    {
        $this->registry->shouldReceive('getPipeline')
            ->once()
            ->with('default')
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->serializerRegistry->shouldReceive('getSerializer')
            ->twice()
            ->with('foo')
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $serializer->shouldReceive('serialize')
            ->once()
            ->with($payload1 = 'foo=bar')
            ->andReturn($serializedPayload1 = 'serialized-payload1');

        $serializer->shouldReceive('serialize')
            ->once()
            ->with($payload2 = ['foo' => 'bar'])
            ->andReturn($serializedPayload1 = 'serialized-payload1');

        $queuedTask = m::mock(QueuedTaskInterface::class);
        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id1');
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id2');

        $queue->shouldReceive('dispatch')->twice()->with($preparedTask)->andReturn($queuedTask);
        $queue->shouldReceive('create')->twice()->andReturn($preparedTask);

        Assert::same($this->queue->push('foo', $payload1), 'task-id1');
        Assert::same($this->queue->push('foo', $payload2), 'task-id2');
    }

    #[Test]
    public function taskShouldBePushedToCustomQueue(): void
    {
        $this->registry->shouldReceive('getPipeline')
            ->once()
            ->with('foo')
            ->andReturn($fooQueue = m::mock(QueueInterface::class));

        $this->registry->shouldReceive('getPipeline')
            ->once()
            ->with('bar')
            ->andReturn($barQueue = m::mock(QueueInterface::class));


        $this->serializerRegistry->shouldReceive('getSerializer')
            ->andReturn($serializer = m::mock(SerializerInterface::class));
        $serializer->shouldReceive('serialize')->andReturn('serialized-payload1');

        $queuedTask = m::mock(QueuedTaskInterface::class);
        $preparedTask = m::mock(PreparedTaskInterface::class);
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id1');
        $queuedTask->shouldReceive('getId')->once()->andReturn('task-id2');

        $fooQueue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $fooQueue->shouldReceive('create')->once()->withSomeOfArgs('foo')->andReturn($preparedTask);

        $barQueue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $barQueue->shouldReceive('create')->once()->withSomeOfArgs('bar')->andReturn($preparedTask);

        Assert::same($this->queue->push('foo', ['foo' => 'bar'], QueueOptions::onQueue('foo')), 'task-id1');
        Assert::same($this->queue->push('bar', ['foo' => 'bar'], QueueOptions::onQueue('bar')), 'task-id2');
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->registry = m::mock(PipelineRegistryInterface::class);
        $this->serializerRegistry = m::mock(SerializerRegistryInterface::class);

        $this->queue = new Queue($this->serializerRegistry, $this->registry, 'default');
    }
}
