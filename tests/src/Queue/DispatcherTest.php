<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Boot\FinalizerInterface;
use Spiral\Queue\Failed\FailedJobHandlerInterface;
use Spiral\Queue\HandlerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\Dispatcher;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
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

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getId')->once()->andReturn('foo-id');
        $task->shouldReceive('getPayload')->once()->andReturn(['foo-payload']);
        $task->shouldReceive('complete')->once();

        $handler = m::mock(HandlerInterface::class);
        $handler->shouldReceive('handle')->with('foo-task', 'foo-id', ['foo-payload']);

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

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $failedJobHandler = $this->mockContainer(FailedJobHandlerInterface::class);
        $failedJobHandler->shouldReceive('handle')->once()->with(
            'roadrunner',
            'queue-name',
            'foo-task',
            ['foo-payload'],
            $e
        );

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getQueue')->once()->andReturn('queue-name');
        $task->shouldReceive('getPayload')->once()->andReturn(['foo-payload']);
        $task->shouldReceive('fail')->once()->with($e);

        $handlerRegistry = $this->mockContainer(HandlerRegistryInterface::class);
        $handlerRegistry->shouldReceive('getHandler')->once()->with('foo-task')->andThrow($e);

        $consumer = $this->mockContainer(ConsumerInterface::class);
        $consumer->shouldReceive('waitTask')->once()->andReturn($task);
        $consumer->shouldReceive('waitTask')->once()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }
}
