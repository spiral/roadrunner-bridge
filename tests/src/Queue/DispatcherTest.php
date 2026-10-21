<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\App\Job\ScopedJob;
use Spiral\Boot\FinalizerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\JobHandler;
use Spiral\Queue\Task;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\Internal\Dispatcher;
use Spiral\RoadRunnerBridge\Queue\PayloadDeserializerInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Testing\Attribute\Config;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class DispatcherTest extends TestCase
{
    #[Test]
    public function canServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertDispatcherCannotBeServed(Dispatcher::class);
    }

    #[Test]
    public function canServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Jobs);
        $this->assertDispatcherCanBeServed(Dispatcher::class);
    }

    #[Test]
    public function serveReceivedTask(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Jobs);

        $deserializer = $this->mockContainer(PayloadDeserializerInterface::class);
        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getId')->once()->andReturn('foo-id');
        $task->shouldReceive('getHeaders')->once()->andReturn(['foo-headers']);
        $task->shouldReceive('getQueue')->once()->andReturn('default');
        $task->shouldReceive('ack')->once();

        $deserializer->shouldReceive('deserialize')
            ->once()
            ->with($task)
            ->andReturn($payload = ['foo-id', 'bar-payload']);

        $handler = m::mock(JobHandler::class);
        $handler
            ->shouldReceive('handle')
            ->with('foo-task', 'foo-id', $payload, ['foo-headers']);

        $handlerRegistry = $this->mockContainer(HandlerRegistryInterface::class);
        $handlerRegistry->shouldReceive('getHandler')->once()->with('foo-task')->andReturn($handler);

        $consumer = $this->mockContainer(ConsumerInterface::class);
        $consumer->shouldReceive('waitTask')->once()->andReturn($task);
        $consumer->shouldReceive('waitTask')->once()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    #[Test]
    public function serveReceivedTaskWithThrownException(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Jobs);
        $deserializer = $this->mockContainer(PayloadDeserializerInterface::class);

        $e = new \Exception('Something went wrong');
        $deserializer->shouldReceive('deserialize')->once()->andReturn(['foo-id', 'bar-payload']);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getId')->andReturn('foo-id');
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getQueue')->once()->andReturn('queue-name');
        $task->shouldReceive('getHeaders')->once()->andReturn(['foo-headers']);
        $task->shouldReceive('nack')->once()->with($e);

        $handler = $this->mockContainer(HandlerRegistryInterface::class);
        $handler->shouldReceive('getHandler')->andThrow($e);

        $consumer = $this->mockContainer(ConsumerInterface::class);
        $consumer->shouldReceive('waitTask')->once()->andReturn($task);
        $consumer->shouldReceive('waitTask')->once()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    #[Config('queue.registry.handlers', ['foo-task' => ScopedJob::class])]
    #[Test]
    public function queueScopes(): void
    {
        Assert::same(ScopedJob::$scopes, []);

        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Jobs);

        $deserializer = $this->mockContainer(PayloadDeserializerInterface::class);
        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $task = m::mock(ReceivedTaskInterface::class);
        $task->shouldReceive('getName')->andReturn('foo-task');
        $task->shouldReceive('getId')->once()->andReturn('foo-id');
        $task->shouldReceive('getHeaders')->once()->andReturn(['foo' => ['foo-headers']]);
        $task->shouldReceive('getQueue')->once()->andReturn('default');
        $task->shouldReceive('ack')->once();

        $deserializer->shouldReceive('deserialize')
            ->once()
            ->with($task)
            ->andReturn($payload = ['foo' => 'foo-id', 'bar' => 'bar-payload']);

        $handler = m::mock(JobHandler::class);
        $handler
            ->shouldReceive('handle')
            ->with('foo-task', 'foo-id', $payload, ['foo' => ['foo-headers']]);

        $consumer = $this->mockContainer(ConsumerInterface::class);
        $consumer->shouldReceive('waitTask')->once()->andReturn($task);
        $consumer->shouldReceive('waitTask')->once()->andReturnNull();

        $this->getApp()->serve();

        Assert::equals(\array_values(\array_filter(ScopedJob::$scopes)), ['queue-task', 'queue', 'root']);
        Assert::equals(ScopedJob::$task, new Task(
            id: 'foo-id',
            queue: 'default',
            name: 'foo-task',
            payload: $payload,
            headers: ['foo' => ['foo-headers']],
        ));
    }
}
