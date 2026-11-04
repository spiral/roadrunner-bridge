<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Hamcrest\Matchers;
use Mockery as m;
use Psr\Log\LoggerInterface;
use RoadRunner\Lock\LockInterface;
use Spiral\Goridge\RPC\Exception\CodecException;
use Spiral\Goridge\RPC\Exception\RPCException;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\Queue\Options;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\Options as JobsOptions;
use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\Queue\Kafka\ConsumerOptions;
use Spiral\RoadRunner\Jobs\Queue\KafkaCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\Internal\RPCPipelineRegistry;
use Spiral\RoadRunnerBridge\Queue\JobOptions;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Tests\Queue\Fixtures\MemoryLock;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

final class RPCPipelineRegistryTest extends TestCase
{
    private RPCPipelineRegistry $registry;
    private JobsInterface|m\MockInterface $jobs;
    private LoggerInterface|m\MockInterface $logger;
    private MemoryLock $lock;
    private MemoryCreateInfo $memory;
    private MemoryCreateInfo $local;
    private AMQPCreateInfo $amqp;
    private MemoryCreateInfo $mail;

    #[Test]
    public function declaresAndResumesMissingConsumerPipelines(): void
    {
        $this->makeRegistry();
        $this->existing('amqp');

        $this->jobs->shouldReceive('create')->once()->with($this->memory, null)->andReturn($memory = $this->queue());
        $memory->shouldReceive('resume')->once();
        $this->jobs->shouldReceive('create')->once()->with($this->local, null)->andReturn($local = $this->queue());
        $local->shouldReceive('resume')->once();
        $this->jobs->shouldNotReceive('create')->with($this->amqp, m::any());

        $this->registry->declareConsumerPipelines();
    }

    #[Test]
    public function consumerPipelinesAreNotDeclaredOutsideJobsMode(): void
    {
        $this->makeRegistry(RoadRunnerMode::Http);
        $this->jobs->shouldNotReceive('getIterator');

        $this->registry->declareConsumerPipelines();
    }

    #[Test]
    public function consumerPipelineDeclaredByAnotherWorkerIsLeftAsIs(): void
    {
        $this->makeRegistry();
        # The list read under the declaration lock shows what the previous lock holder declared.
        $this->jobs->shouldReceive('getIterator')->andReturn(
            new \ArrayIterator(['amqp' => '']),
            new \ArrayIterator(['amqp' => '', 'memory' => '']),
            new \ArrayIterator(['amqp' => '', 'memory' => '', 'local' => '']),
        );
        $this->jobs->shouldNotReceive('create');
        $this->jobs->shouldReceive('connect')->twice()->andReturn($this->queue());
        $this->logger->shouldNotReceive('warning');

        $this->registry->declareConsumerPipelines();
    }

    #[Test]
    public function failedDeclarationIsLogged(): void
    {
        $this->makeRegistry();
        $this->existing('amqp', 'local');
        $this->jobs->shouldReceive('create')->once()->with($this->memory, null)->andThrow(new JobsException('no driver'));
        $this->logger->shouldReceive('warning')->once()
            ->with('Unable to declare consumer pipeline "memory". Reason: no driver');

        $this->registry->declareConsumerPipelines();
    }

    #[Test]
    public function connectsToExistingPipelineWithItsDefaultOptions(): void
    {
        $this->makeRegistry();
        $this->existing('mail-rr');
        $this->jobs->shouldReceive('connect')->once()
            ->with('mail-rr', Matchers::equalTo(new JobsOptions(5)))
            ->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('mail'), $queue);
    }

    #[Test]
    public function jobOptionsFromConfigBecomePipelineDefaults(): void
    {
        $this->makeRegistry(pipelines: [
            'mail' => [
                'connector' => new MemoryCreateInfo('mail-rr'),
                'options' => JobOptions::delayed(5)->withPriority(2)->withAutoAck(true),
            ],
        ]);
        $this->existing('mail-rr');
        $this->jobs->shouldReceive('connect')->once()
            ->with('mail-rr', Matchers::equalTo(new JobsOptions(5, 2, true)))
            ->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('mail'), $queue);
    }

    #[Test]
    public function roadRunnerNameOfConfiguredPipelineGetsItsDefaultOptions(): void
    {
        $this->makeRegistry();
        $this->existing('mail-rr');
        $this->jobs->shouldReceive('connect')->once()
            ->with('mail-rr', Matchers::equalTo(new JobsOptions(5)))
            ->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('mail-rr'), $queue);
    }

    #[Test]
    public function jobsOptionsFromConfigArePassedAsIs(): void
    {
        $this->makeRegistry();
        $this->existing('orders');
        $this->jobs->shouldReceive('connect')->once()
            ->with('orders', Matchers::equalTo(new KafkaOptions('orders', 100, 14)))
            ->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('orders'), $queue);
    }

    #[Test]
    public function kafkaPipelineWithPlainOptionsIsRejected(): void
    {
        $this->makeRegistry(pipelines: [
            'events' => ['connector' => new KafkaCreateInfo('events'), 'options' => Options::delayed(3)],
        ]);
        $this->jobs->shouldNotReceive('connect');
        $this->jobs->shouldNotReceive('create');
        Expect::exception(InvalidArgumentException::class)->withMessage(
            'Pipeline `events` has a Kafka connector: its `options` must be `Spiral\RoadRunner\Jobs\KafkaOptions` with the topic to push to.',
        );

        $this->registry->getPipeline('events');
    }

    #[Test]
    public function kafkaPipelineWithoutOptionsPushesToItsConsumerTopic(): void
    {
        $this->makeRegistry(pipelines: ['events' => [
            'connector' => new KafkaCreateInfo('events', consumerOptions: new ConsumerOptions(['orders'])),
        ]]);
        $this->existing('events');
        $this->jobs->shouldReceive('connect')->once()
            ->with('events', Matchers::equalTo(new KafkaOptions('orders')))
            ->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('events'), $queue);
    }

    #[Test]
    public function kafkaPipelineWithoutTopicPushesToTheDefaultTopic(): void
    {
        $this->makeRegistry(pipelines: ['events' => ['connector' => new KafkaCreateInfo('events')]]);
        $this->existing('events');
        $this->jobs->shouldReceive('connect')->once()
            ->with('events', Matchers::equalTo(new KafkaOptions('default')))
            ->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('events'), $queue);
    }

    #[Test]
    public function missingKafkaPipelineIsCreatedWithItsConsumerTopic(): void
    {
        $connector = new KafkaCreateInfo('events', consumerOptions: new ConsumerOptions(['orders']));
        $this->makeRegistry(pipelines: ['events' => ['connector' => $connector]]);
        $this->existing();
        $this->jobs->shouldReceive('create')->once()
            ->with($connector, Matchers::equalTo(new KafkaOptions('orders')))
            ->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('events'), $queue);
    }

    #[Test]
    public function missingPipelineIsCreatedPaused(): void
    {
        $this->makeRegistry();
        $this->existing();
        $this->jobs->shouldReceive('create')->once()
            ->with($this->mail, Matchers::equalTo(new JobsOptions(5)))
            ->andReturn($queue = $this->queue());
        $queue->shouldNotReceive('resume');

        Assert::same($this->registry->getPipeline('mail'), $queue);
    }

    #[Test]
    public function missingConsumerPipelineIsCreatedAndResumed(): void
    {
        $this->makeRegistry();
        $this->existing();
        $this->jobs->shouldReceive('create')->once()->with($this->memory, null)->andReturn($queue = $this->queue());
        $queue->shouldReceive('resume')->once();

        Assert::same($this->registry->getPipeline('memory'), $queue);
    }

    #[Test]
    public function failedResumeIsLoggedAndQueueReturned(): void
    {
        $this->makeRegistry();
        $this->existing();
        $this->jobs->shouldReceive('create')->once()->andReturn($queue = $this->queue());
        $queue->shouldReceive('resume')->once()->andThrow(new JobsException('rpc error'));
        $this->logger->shouldReceive('warning')->once()
            ->with('Unable to resume pipeline "memory". Reason: rpc error');

        Assert::same($this->registry->getPipeline('memory'), $queue);
    }

    #[Test]
    public function pipelineCreatedOutsideTheLockIsConnected(): void
    {
        $this->makeRegistry();
        $this->jobs->shouldReceive('getIterator')->times(3)->andReturn(
            new \ArrayIterator([]),
            new \ArrayIterator([]),
            new \ArrayIterator(['memory' => '']),
        );
        $this->jobs->shouldReceive('create')->once()
            ->andThrow(new JobsException('rpc_declare_pipeline: pipeline already exists, name: memory'));
        $this->jobs->shouldReceive('connect')->once()->with('memory', null)->andReturn($queue = $this->queue());
        # Its creator resumes it, or leaves it paused on purpose.
        $queue->shouldNotReceive('resume');
        $this->logger->shouldReceive('warning')->once()->with(
            'Pipeline "memory" was declared by a process outside the declaration lock: another bridge version or `lockPrefix`, or a declaration that outlived the lock.',
        );

        Assert::same($this->registry->getPipeline('memory'), $queue);
    }

    #[Test]
    public function createErrorIsThrownWhenThePipelineIsStillMissing(): void
    {
        $this->makeRegistry();
        $this->existing();
        $this->jobs->shouldReceive('create')->once()->andThrow(new JobsException('no driver'));
        Expect::exception(JobsException::class)->withMessage('no driver');

        $this->registry->getPipeline('mail');
    }

    #[Test]
    public function createErrorIsThrownWhenTheListCannotBeRead(): void
    {
        $this->makeRegistry();
        $this->jobs->shouldReceive('getIterator')->times(3)->andReturnUsing(
            static fn(): \Traversable => new \ArrayIterator([]),
            static fn(): \Traversable => new \ArrayIterator([]),
            static fn(): never => throw new JobsException('rpc down'),
        );
        $this->jobs->shouldReceive('create')->once()->andThrow(new JobsException('no driver'));
        Expect::exception(JobsException::class)->withMessage('no driver');

        $this->registry->getPipeline('mail');
    }

    #[Test]
    public function pipelineDeclaredAtBootIsConnectedOnFirstPush(): void
    {
        $this->makeRegistry();
        $declared = ['amqp' => ''];
        $this->jobs->shouldReceive('getIterator')->andReturnUsing(
            static function () use (&$declared): \Traversable {
                return new \ArrayIterator($declared);
            },
        );
        $this->jobs->shouldReceive('create')->twice()->andReturnUsing(
            function (CreateInfoInterface $connector) use (&$declared): QueueInterface {
                $declared[$connector->getName()] = '';
                $queue = $this->queue();
                $queue->shouldReceive('resume')->once();

                return $queue;
            },
        );
        $this->registry->declareConsumerPipelines();

        $this->jobs->shouldReceive('connect')->once()->with('memory', null)->andReturn($connected = $this->queue());

        Assert::same($this->registry->getPipeline('memory'), $connected);
    }

    #[Test]
    public function declarationHoldsTheLock(): void
    {
        $this->makeRegistry();
        $this->existing();
        $this->jobs->shouldReceive('create')->once()->with($this->memory, null)->andReturn($queue = $this->queue());
        $queue->shouldReceive('resume')->once();

        $this->registry->getPipeline('memory');

        Assert::same($this->lock->log, ['lock app:declare:memory ttl=20 wait=10', 'release app:declare:memory']);
        Assert::same($this->lock->locks, []);
    }

    #[Test]
    public function failedDeclarationReleasesTheLock(): void
    {
        $this->makeRegistry();
        $this->existing();
        $this->jobs->shouldReceive('create')->once()->andThrow(new JobsException('no driver'));

        try {
            $this->registry->getPipeline('memory');
        } catch (JobsException) {
        }

        Assert::same($this->lock->locks, []);
    }

    #[Test]
    public function existingConsumerPipelineIsLeftAsIsAtBoot(): void
    {
        $this->makeRegistry();
        $this->existing('memory', 'local', 'amqp');
        $this->jobs->shouldNotReceive('create');
        $this->jobs->shouldNotReceive('connect');

        $this->registry->declareConsumerPipelines();

        Assert::same($this->lock->log, []);
    }

    #[Test]
    public function pipelineBeingDeclaredElsewhereFailsAfterTheWait(): void
    {
        $this->makeRegistry();
        $this->existing();
        $this->lock->locks['app:declare:mail-rr'] = 'other';
        $this->jobs->shouldNotReceive('create');
        Expect::exception(JobsException::class)
            ->withMessage('Pipeline "mail-rr" is still being declared by another process.');

        $this->registry->getPipeline('mail');
    }

    #[Test]
    public function consumerPipelineBeingDeclaredElsewhereIsLogged(): void
    {
        $this->makeRegistry();
        $this->existing('amqp', 'local');
        $this->lock->locks['app:declare:memory'] = 'other';
        $this->jobs->shouldNotReceive('create');
        $this->logger->shouldReceive('warning')->once()->with(
            'Unable to declare consumer pipeline "memory". Reason: Pipeline "memory" is still being declared by another process.',
        );

        $this->registry->declareConsumerPipelines();
    }

    #[Test]
    public function pipelineSharedWithConsumingKeyIsResumed(): void
    {
        $shared = new MemoryCreateInfo('mail-rr');
        $this->makeRegistry(pipelines: [
            'mail' => ['connector' => $shared, 'consume' => true],
            'mail-slow' => ['connector' => $shared, 'options' => Options::delayed(60)],
        ]);
        $this->existing();
        $this->jobs->shouldReceive('create')->once()
            ->with($shared, Matchers::equalTo(new JobsOptions(60)))
            ->andReturn($queue = $this->queue());
        $queue->shouldReceive('resume')->once();

        Assert::same($this->registry->getPipeline('mail-slow'), $queue);
    }

    #[Test]
    public function missingPipelineNamedByItsRoadRunnerNameIsCreatedWithDefaults(): void
    {
        $this->makeRegistry();
        $this->existing();
        $this->jobs->shouldReceive('create')->once()
            ->with($this->mail, Matchers::equalTo(new JobsOptions(5)))
            ->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('mail-rr'), $queue);
    }

    #[Test]
    public function pipelinesKeyWinsOverConnectorName(): void
    {
        $this->makeRegistry(pipelines: [
            'a' => ['connector' => new MemoryCreateInfo('b')],
            'b' => ['connector' => new MemoryCreateInfo('c')],
        ]);
        $this->existing('b', 'c');
        $this->jobs->shouldReceive('connect')->once()->with('c', null)->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('b'), $queue);
    }

    #[Test]
    public function lockFailureIsReportedAsJobsFailure(): void
    {
        $lock = m::mock(LockInterface::class);
        $lock->shouldReceive('lock')->andThrow(new RPCException('lock.Lock: unknown service'));
        $registry = new RPCPipelineRegistry(
            m::mock(LoggerInterface::class),
            $jobs = m::mock(JobsInterface::class),
            RoadRunnerMode::Http,
            $lock,
            new QueueConfig(['mail' => ['connector' => new MemoryCreateInfo('mail')]]),
        );
        $jobs->shouldReceive('getIterator')->andReturn(new \ArrayIterator([]));
        Expect::exception(JobsException::class)->withMessage(
            'RoadRunner lock call failed, check that the RoadRunner build includes the `lock` plugin: lock.Lock: unknown service',
        );

        $registry->getPipeline('mail');
    }

    #[Test]
    public function failedLockReleaseIsLoggedAndTheQueueReturned(): void
    {
        $lock = m::mock(LockInterface::class);
        $lock->shouldReceive('lock')->andReturn('id');
        $lock->shouldReceive('release')->andThrow(new RPCException('rpc down'));
        $registry = new RPCPipelineRegistry(
            $logger = m::mock(LoggerInterface::class),
            $jobs = m::mock(JobsInterface::class),
            RoadRunnerMode::Http,
            $lock,
            new QueueConfig(['mail' => ['connector' => $connector = new MemoryCreateInfo('mail')]]),
        );
        $jobs->shouldReceive('getIterator')->andReturn(new \ArrayIterator([]));
        $jobs->shouldReceive('create')->once()->with($connector, null)->andReturn($queue = $this->queue());
        $logger->shouldReceive('warning')->once()
            ->with('Unable to release the lock "roadrunner-bridge:jobs:declare:mail". Reason: rpc down');

        Assert::same($registry->getPipeline('mail'), $queue);
    }

    #[Test]
    public function expiredLockIsLogged(): void
    {
        $lock = m::mock(LockInterface::class);
        $lock->shouldReceive('lock')->andReturn('id');
        $lock->shouldReceive('release')->once()->with('roadrunner-bridge:jobs:declare:mail', 'id')->andReturnFalse();
        $registry = new RPCPipelineRegistry(
            $logger = m::mock(LoggerInterface::class),
            $jobs = m::mock(JobsInterface::class),
            RoadRunnerMode::Http,
            $lock,
            new QueueConfig(['mail' => ['connector' => new MemoryCreateInfo('mail')]]),
        );
        $jobs->shouldReceive('getIterator')->andReturn(new \ArrayIterator([]));
        $jobs->shouldReceive('create')->once()->andReturn($queue = $this->queue());
        $logger->shouldReceive('warning')->once()->with(
            'The lock "roadrunner-bridge:jobs:declare:mail" expired before the declaration ended, so another process may have declared the pipeline too.',
        );

        Assert::same($registry->getPipeline('mail'), $queue);
    }

    #[Test]
    public function undecodableLockResponseIsReportedAsJobsFailure(): void
    {
        $lock = m::mock(LockInterface::class);
        $lock->shouldReceive('lock')->andThrow(new CodecException('unable to unmarshal'));
        $registry = new RPCPipelineRegistry(
            $logger = m::mock(LoggerInterface::class),
            $jobs = m::mock(JobsInterface::class),
            RoadRunnerMode::Jobs,
            $lock,
            new QueueConfig(['mail' => ['connector' => new MemoryCreateInfo('mail'), 'consume' => true]]),
        );
        $jobs->shouldReceive('getIterator')->andReturn(new \ArrayIterator([]));
        $jobs->shouldNotReceive('create');
        $logger->shouldReceive('warning')->once()->with(m::pattern('/^Unable to declare consumer pipeline "mail". Reason: .*unable to unmarshal$/'));

        $registry->declareConsumerPipelines();
    }

    #[Test]
    public function unknownPipelineIsConnectedByName(): void
    {
        $this->makeRegistry();
        $this->jobs->shouldReceive('connect')->once()->with('test')->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('test'), $queue);
    }

    #[Test]
    public function pipelineWithoutConnectorThrows(): void
    {
        $this->makeRegistry();
        Expect::exception(InvalidArgumentException::class)
            ->withMessageContaining('You must specify connector for given pipeline `without-connector`.');

        $this->registry->getPipeline('without-connector');
    }

    #[Test]
    public function pipelineWithWrongConnectorThrows(): void
    {
        $this->makeRegistry();
        Expect::exception(InvalidArgumentException::class)
            ->withMessageContaining('Connector should implement Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface interface.');

        $this->registry->getPipeline('with-wrong-connector');
    }

    private function makeRegistry(RoadRunnerMode $mode = RoadRunnerMode::Jobs, ?array $pipelines = null): void
    {
        $this->memory = new MemoryCreateInfo('memory');
        $this->local = new MemoryCreateInfo('local');
        $this->amqp = new AMQPCreateInfo('amqp');
        $this->mail = new MemoryCreateInfo('mail-rr');

        $this->registry = new RPCPipelineRegistry(
            $this->logger = m::mock(LoggerInterface::class),
            $this->jobs = m::mock(JobsInterface::class),
            $mode,
            $this->lock = new MemoryLock(),
            new QueueConfig($pipelines ?? [
                'memory' => ['connector' => $this->memory, 'consume' => true],
                'local' => ['connector' => $this->local, 'consume' => true],
                'amqp' => ['connector' => $this->amqp, 'consume' => true],
                'mail' => ['connector' => $this->mail, 'options' => Options::delayed(5)],
                'orders' => ['connector' => new KafkaCreateInfo('orders'), 'options' => new KafkaOptions('orders', 100, 14)],
                'without-connector' => ['consume' => false],
                'with-wrong-connector' => ['connector' => 'test', 'consume' => false],
            ], 'app:'),
            60,
        );
    }

    private function existing(string ...$names): void
    {
        $this->jobs->shouldReceive('getIterator')->andReturnUsing(static fn(): \Traversable => new \ArrayIterator(\array_fill_keys($names, '')));
    }

    private function queue(): QueueInterface|m\MockInterface
    {
        return m::mock(QueueInterface::class);
    }
}
