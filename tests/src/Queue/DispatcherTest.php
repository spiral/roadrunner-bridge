<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Boot\FinalizerInterface;
use Spiral\Queue\Failed\FailedJobHandlerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\JobHandler;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\Dispatcher;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Serializer\SerializerInterface;
use Spiral\Tests\TestCase;

final class DispatcherTest extends TestCase
{
    public function testCanServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertDispatcherCannotBeServed(Dispatcher::class);
    }

    public function testCanServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Jobs);
        $this->assertDispatcherCanBeServed(Dispatcher::class);
    }

    public function testServeReceivedTask(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Jobs);

        $serializerRegistry = $this->mockContainer(SerializerRegistryInterface::class);
        $serializerRegistry->shouldReceive('getSerializer')
            ->once()
            ->with('foo-task')
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $serializer->shouldReceive('unserialize')
            ->once()
            ->with('foo-payload')
            ->andReturn('bar-payload');

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getId')->once()->andReturn('foo-id');
        $task->shouldReceive('getPayload')->once()->andReturn('foo-payload');
        $task->shouldReceive('getHeaders')->once()->andReturn(['foo-headers']);
        $task->shouldReceive('getQueue')->once()->andReturn('default');
        $task->shouldReceive('complete')->once();

        $task->shouldReceive('hasHeader')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturnFalse();

        $handler = m::mock(JobHandler::class);
        $handler
            ->shouldReceive('handle')
            ->with('foo-task', 'foo-id', 'bar-payload', ['foo-headers']);

        $handlerRegistry = $this->mockContainer(HandlerRegistryInterface::class);
        $handlerRegistry->shouldReceive('getHandler')->once()->with('foo-task')->andReturn($handler);

        $consumer = $this->mockContainer(ConsumerInterface::class);
        $consumer->shouldReceive('waitTask')->once()->andReturn($task);
        $consumer->shouldReceive('waitTask')->once()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    public function testServeReceivedTaskWithSerializedClassInHeader(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Jobs);

        $serializerRegistry = $this->mockContainer(SerializerRegistryInterface::class);
        $serializerRegistry->shouldReceive('getSerializer')
            ->once()
            ->with('foo-task')
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $serializer->shouldReceive('unserialize')
            ->once()
            ->with('foo-payload', \stdClass::class)
            ->andReturn($class = new \stdClass());

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getId')->once()->andReturn('foo-id');
        $task->shouldReceive('getPayload')->once()->andReturn('foo-payload');
        $task->shouldReceive('getHeaders')->once()->andReturn(['foo-headers']);
        $task->shouldReceive('getQueue')->once()->andReturn('default');
        $task->shouldReceive('complete')->once();

        $task->shouldReceive('hasHeader')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturnTrue();

        $task->shouldReceive('getHeaderLine')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturn(\stdClass::class);

        $handler = m::mock(JobHandler::class);
        $handler
            ->shouldReceive('handle')
            ->with('foo-task', 'foo-id', $class, ['foo-headers']);

        $handlerRegistry = $this->mockContainer(HandlerRegistryInterface::class);
        $handlerRegistry->shouldReceive('getHandler')->once()->with('foo-task')->andReturn($handler);

        $consumer = $this->mockContainer(ConsumerInterface::class);
        $consumer->shouldReceive('waitTask')->once()->andReturn($task);
        $consumer->shouldReceive('waitTask')->once()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    public function testServeReceivedTaskWithThrownException(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Jobs);

        $e = new \Exception('Something went wrong');

        $serializerRegistry = $this->mockContainer(SerializerRegistryInterface::class);
        $serializerRegistry->shouldReceive('getSerializer')
            ->once()
            ->with('foo-task')
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $serializer->shouldReceive('unserialize')
            ->once()
            ->with('foo-payload')
            ->andReturn($payload = ['bar-payload']);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $failedJobHandler = $this->mockContainer(FailedJobHandlerInterface::class);
        $failedJobHandler->shouldReceive('handle')->once()->with(
            'roadrunner',
            'queue-name',
            'foo-task',
            $payload,
            $e,
        );

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getId')->andReturn('foo-id');
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getQueue')->once()->andReturn('queue-name');
        $task->shouldReceive('getPayload')->once()->andReturn('foo-payload');
        $task->shouldReceive('getHeaders')->once()->andReturn(['foo-headers']);
        $task->shouldReceive('fail')->once()->with($e);

        $task->shouldReceive('hasHeader')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturnFalse();


        $handler = $this->mockContainer(HandlerRegistryInterface::class);
        $handler->shouldReceive('getHandler')->andThrow($e);

        $consumer = $this->mockContainer(ConsumerInterface::class);
        $consumer->shouldReceive('waitTask')->once()->andReturn($task);
        $consumer->shouldReceive('waitTask')->once()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }
}
