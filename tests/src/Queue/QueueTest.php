<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\Queue\Options as QueueOptions;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\Options as JobsOptions;
use Spiral\RoadRunner\Jobs\Queue as RoadRunnerQueue;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;
use Spiral\RoadRunner\Jobs\Task\QueuedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\Internal\Queue;
use Spiral\RoadRunnerBridge\Queue\Options as BridgeOptions;
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
        $queue->shouldReceive('getDefaultOptions')->andReturn(new JobsOptions());

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
        $fooQueue->shouldReceive('getDefaultOptions')->andReturn(new JobsOptions());

        $barQueue->shouldReceive('dispatch')->once()->with($preparedTask)->andReturn($queuedTask);
        $barQueue->shouldReceive('create')->once()->withSomeOfArgs('bar')->andReturn($preparedTask);
        $barQueue->shouldReceive('getDefaultOptions')->andReturn(new JobsOptions());

        Assert::same($this->queue->push('foo', ['foo' => 'bar'], QueueOptions::onQueue('foo')), 'task-id1');
        Assert::same($this->queue->push('bar', ['foo' => 'bar'], QueueOptions::onQueue('bar')), 'task-id2');
    }

    #[Test]
    public function pushHeadersAreAddedToPipelineDefaultHeaders(): void
    {
        $task = $this->pushTo(
            (new JobsOptions())->withHeader('x-default', 'd')->withHeader('x-both', 'default'),
            QueueOptions::onQueue('default')->withHeader('x-push', 'p')->withHeader('x-both', 'push'),
        );

        Assert::same($task->getHeaders(), ['x-push' => ['p'], 'x-both' => ['push'], 'x-default' => ['d']]);
    }

    #[Test]
    public function pipelineDefaultHeadersAreKeptWithoutPushHeaders(): void
    {
        $task = $this->pushTo((new JobsOptions())->withHeader('x-default', 'd'), null);

        Assert::same($task->getHeaders(), ['x-default' => ['d']]);
    }

    #[Test]
    public function explicitZeroDelayOverridesPipelineDefault(): void
    {
        $task = $this->pushTo(new JobsOptions(10), QueueOptions::delayed(0));

        Assert::same($task->getDelay(), 0);
    }

    #[Test]
    public function pipelineDefaultDelayAppliesWhenPushSetsNone(): void
    {
        $task = $this->pushTo(new JobsOptions(10), QueueOptions::onQueue('default'));

        Assert::same($task->getDelay(), 10);
    }

    #[Test]
    public function pushDelayOverridesPipelineDefault(): void
    {
        $task = $this->pushTo(new JobsOptions(10), QueueOptions::delayed(3));

        Assert::same($task->getDelay(), 3);
    }

    #[Test]
    public function bridgeOptionsSetQueueAndPriority(): void
    {
        $this->queue = new Queue($this->serializerRegistry, $this->registry, 'other');

        $task = $this->pushTo(new JobsOptions(priority: 10), BridgeOptions::onQueue('default')->withPriority(3));

        Assert::same($task->getPriority(), 3);
    }

    #[Test]
    public function pipelineDefaultPriorityAppliesWhenPushSetsNone(): void
    {
        $task = $this->pushTo(new JobsOptions(priority: 10), BridgeOptions::delayed(5));

        Assert::same($task->getPriority(), 10);
        Assert::same($task->getDelay(), 5);
    }

    #[Test]
    public function explicitFalseAutoAckOverridesPipelineDefault(): void
    {
        $task = $this->pushTo(new JobsOptions(autoAck: true), BridgeOptions::onQueue('default')->withAutoAck(false));

        Assert::false($task->getAutoAck());
    }

    #[Test]
    public function pushAutoAckOverridesPipelineDefault(): void
    {
        $task = $this->pushTo(new JobsOptions(), BridgeOptions::onQueue('default')->withAutoAck(true));

        Assert::true($task->getAutoAck());
    }

    #[Test]
    public function pipelineDefaultAutoAckAppliesWhenPushSetsNone(): void
    {
        $task = $this->pushTo(new JobsOptions(autoAck: true), BridgeOptions::onQueue('default'));

        Assert::true($task->getAutoAck());
    }

    #[Test]
    public function pushTopicOverridesKafkaPipelineDefault(): void
    {
        $task = $this->pushTo(new KafkaOptions('default'), new KafkaOptions('refunds'));

        $options = $task->getOptions();
        \assert($options instanceof KafkaOptions);
        Assert::same($options->getTopic(), 'refunds');
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->registry = m::mock(PipelineRegistryInterface::class);
        $this->serializerRegistry = m::mock(SerializerRegistryInterface::class);

        $this->queue = new Queue($this->serializerRegistry, $this->registry, 'default');
    }

    /**
     * Pushes through a real RoadRunner queue with the given defaults and returns the task it dispatches.
     */
    private function pushTo(JobsOptions $defaults, QueueOptions|JobsOptions|null $options): PreparedTaskInterface
    {
        $rpc = m::mock(RPCInterface::class);
        $rpc->shouldReceive('withCodec')->andReturnSelf();
        $real = new RoadRunnerQueue('default', $rpc, $defaults);

        $queue = m::mock(QueueInterface::class);
        $queue->shouldReceive('getDefaultOptions')->andReturn($defaults);
        $queue->shouldReceive('create')->once()->andReturnUsing($real->create(...));
        $queuedTask = m::mock(QueuedTaskInterface::class);
        $queuedTask->shouldReceive('getId')->andReturn('task-id');
        $dispatched = null;
        $queue->shouldReceive('dispatch')->once()->andReturnUsing(
            static function (PreparedTaskInterface $task) use (&$dispatched, $queuedTask): QueuedTaskInterface {
                $dispatched = $task;
                return $queuedTask;
            },
        );

        $this->registry->shouldReceive('getPipeline')->with('default')->andReturn($queue);
        $this->serializerRegistry->shouldReceive('getSerializer')->andReturn($serializer = m::mock(SerializerInterface::class));
        $serializer->shouldReceive('serialize')->andReturn('payload');

        $this->queue->push('job', 'payload', $options);

        \assert($dispatched instanceof PreparedTaskInterface);
        return $dispatched;
    }
}
