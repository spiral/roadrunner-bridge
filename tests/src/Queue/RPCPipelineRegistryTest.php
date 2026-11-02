<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Hamcrest\Matchers;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\Queue\Options;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\Options as JobsOptions;
use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\KafkaCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\Internal\RPCPipelineRegistry;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

final class RPCPipelineRegistryTest extends TestCase
{
    private RPCPipelineRegistry $registry;
    private JobsInterface|m\MockInterface $jobs;
    private LoggerInterface|m\MockInterface $logger;
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
        $this->jobs->shouldReceive('getIterator')->andReturn(
            new \ArrayIterator(['amqp' => '']),
            new \ArrayIterator(['amqp' => '', 'memory' => '']),
            new \ArrayIterator(['amqp' => '', 'memory' => '', 'local' => '']),
        );
        $this->jobs->shouldReceive('create')
            ->twice()
            ->andThrow(new JobsException('rpc_declare_pipeline: pipeline already exists'));
        $this->jobs->shouldReceive('connect')->twice()->andReturn($this->queue());
        $this->logger->shouldNotReceive('warning');

        $this->registry->declareConsumerPipelines();
    }

    #[Test]
    public function failedDeclarationIsLogged(): void
    {
        $this->makeRegistry();
        $this->jobs->shouldReceive('getIterator')->twice()->andReturn(new \ArrayIterator(['amqp' => '', 'local' => '']));
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
    public function kafkaPipelineWithPlainOptionsGetsThemAsKafkaOptions(): void
    {
        $this->makeRegistry(pipelines: [
            'events' => [
                'connector' => new KafkaCreateInfo('events'),
                'options' => Options::delayed(3)->withHeader('x-default', 'd'),
            ],
        ]);
        $this->existing('events');
        $this->jobs->shouldReceive('connect')->once()
            ->with('events', Matchers::equalTo((new KafkaOptions('default', 3))->withHeader('x-default', 'd')))
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
    public function pipelineDeclaredSinceTheListWasCachedIsConnected(): void
    {
        $this->makeRegistry();
        $this->jobs->shouldReceive('getIterator')->twice()->andReturn(
            new \ArrayIterator([]),
            new \ArrayIterator(['memory' => '']),
        );
        $this->jobs->shouldReceive('create')->once()
            ->andThrow(new JobsException('rpc_declare_pipeline: pipeline already exists, name: memory'));
        $this->jobs->shouldReceive('connect')->once()->with('memory', null)->andReturn($queue = $this->queue());

        Assert::same($this->registry->getPipeline('memory'), $queue);
    }

    #[Test]
    public function createErrorIsThrownWhenThePipelineIsStillMissing(): void
    {
        $this->makeRegistry();
        $this->jobs->shouldReceive('getIterator')->twice()->andReturn(new \ArrayIterator([]));
        $this->jobs->shouldReceive('create')->once()->andThrow(new JobsException('no driver'));
        Expect::exception(JobsException::class)->withMessage('no driver');

        $this->registry->getPipeline('mail');
    }

    #[Test]
    public function createErrorIsThrownWhenTheListCannotBeRead(): void
    {
        $this->makeRegistry();
        $this->jobs->shouldReceive('getIterator')->twice()->andReturnUsing(
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
        $this->existing('amqp');
        $this->jobs->shouldReceive('create')->twice()->andReturn($queue = $this->queue());
        $queue->shouldReceive('resume')->twice();
        $this->registry->declareConsumerPipelines();

        $this->jobs->shouldReceive('connect')->once()->with('memory', null)->andReturn($connected = $this->queue());

        Assert::same($this->registry->getPipeline('memory'), $connected);
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
            new QueueConfig($pipelines ?? [
                'memory' => ['connector' => $this->memory, 'consume' => true],
                'local' => ['connector' => $this->local, 'consume' => true],
                'amqp' => ['connector' => $this->amqp, 'consume' => true],
                'mail' => ['connector' => $this->mail, 'options' => Options::delayed(5)],
                'orders' => ['connector' => new KafkaCreateInfo('orders'), 'options' => new KafkaOptions('orders', 100, 14)],
                'without-connector' => ['consume' => false],
                'with-wrong-connector' => ['connector' => 'test', 'consume' => false],
            ]),
            60,
        );
    }

    private function existing(string ...$names): void
    {
        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(\array_fill_keys($names, '')));
    }

    private function queue(): QueueInterface|m\MockInterface
    {
        return m::mock(QueueInterface::class);
    }
}
