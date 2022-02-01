<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Boot\FinalizerInterface;
use Spiral\Queue\Failed\FailedJobHandlerInterface;
use Spiral\Queue\HandlerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\Dispatcher;
use Spiral\Tests\TestCase;

final class DispatcherTest extends TestCase
{
    public function testCanServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertFalse($this->app->get(Dispatcher::class)->canServe());
    }

    public function testCanServe(): void
    {
        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => 'jobs',
            ]);
        });

        $this->assertTrue($this->app->get(Dispatcher::class)->canServe());
    }

    public function testServeReceivedTask(): void
    {
        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => 'jobs',
            ]);
        });

        $finalizer = m::mock(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);
        $this->container->bind(FinalizerInterface::class, $finalizer);

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getId')->once()->andReturn('foo-id');
        $task->shouldReceive('getPayload')->once()->andReturn(['foo-payload']);
        $task->shouldReceive('complete')->once();

        $handler = m::mock(HandlerInterface::class);
        $handler->shouldReceive('handle')->with('foo-task', 'foo-id', ['foo-payload']);

        $handlerRegistry = m::mock(HandlerRegistryInterface::class);
        $this->container->bind(HandlerRegistryInterface::class, $handlerRegistry);
        $handlerRegistry->shouldReceive('getHandler')->once()->with('foo-task')->andReturn($handler);

        $consumer = m::mock(ConsumerInterface::class);
        $this->container->bind(ConsumerInterface::class, $consumer);
        $consumer->shouldReceive('waitTask')->once()->andReturn($task);
        $consumer->shouldReceive('waitTask')->once()->andReturnNull();

        $this->app->get(Dispatcher::class)->serve();
    }


    public function testServeReceivedTaskWithThrownException(): void
    {
        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => 'jobs',
            ]);
        });

        $e = new \Exception('Something went wrong');

        $finalizer = m::mock(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);
        $this->container->bind(FinalizerInterface::class, $finalizer);

        $failedJobHandler = m::mock(FailedJobHandlerInterface::class);
        $failedJobHandler->shouldReceive('handle')->once()->with(
            'roadrunner',
            'queue-name',
            'foo-task',
            ['foo-payload'],
            $e
        );

        $this->container->bind(FailedJobHandlerInterface::class, $failedJobHandler);

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getQueue')->once()->andReturn('queue-name');
        $task->shouldReceive('getPayload')->once()->andReturn(['foo-payload']);
        $task->shouldReceive('fail')->once()->with($e);

        $handlerRegistry = m::mock(HandlerRegistryInterface::class);
        $this->container->bind(HandlerRegistryInterface::class, $handlerRegistry);
        $handlerRegistry->shouldReceive('getHandler')->once()->with('foo-task')->andThrow($e);

        $consumer = m::mock(ConsumerInterface::class);
        $this->container->bind(ConsumerInterface::class, $consumer);
        $consumer->shouldReceive('waitTask')->once()->andReturn($task);
        $consumer->shouldReceive('waitTask')->once()->andReturnNull();

        $this->app->get(Dispatcher::class)->serve();
    }
}
