<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Hamcrest\Matchers;
use Mockery as m;
use Psr\Log\LoggerInterface;
use RoadRunner\Lock\LockInterface;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\Queue\Options;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\Options as JobsOptions;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\Queue\Driver;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\RPCPipelineRegistry;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Tests\TestCase;

final class RPCPipelineRegistryTest extends TestCase
{
    private CreateInfoInterface|m\LegacyMockInterface|m\MockInterface $memoryConnector;
    private CreateInfoInterface|m\LegacyMockInterface|m\MockInterface $localConnector;
    private RPCPipelineRegistry $registry;
    private m\LegacyMockInterface|m\MockInterface|CreateInfoInterface $amqpConnector;
    private JobsInterface|m\MockInterface|m\LegacyMockInterface $jobs;
    private LoggerInterface|m\LegacyMockInterface|m\MockInterface $logger;
    private LockInterface|m\MockInterface|m\LegacyMockInterface $lock;

    public function makeJob(RoadRunnerMode $mode = RoadRunnerMode::Jobs): void
    {
        $this->memoryConnector = m::mock(CreateInfoInterface::class);
        $this->memoryConnector->shouldReceive('toArray')->andReturn([]);
        $this->memoryConnector->shouldReceive('getDriver')->andReturn(Driver::Memory);

        $this->localConnector = m::mock(CreateInfoInterface::class);
        $this->localConnector->shouldReceive('toArray')->andReturn([]);
        $this->localConnector->shouldReceive('getDriver')->andReturn(Driver::SQS);

        $this->amqpConnector = m::mock(CreateInfoInterface::class);
        $this->amqpConnector->shouldReceive('toArray')->andReturn([]);
        $this->amqpConnector->shouldReceive('getDriver')->andReturn(Driver::AMQP);

        $this->registry = new RPCPipelineRegistry(
            $this->logger = m::mock(LoggerInterface::class),
            $this->jobs = m::mock(JobsInterface::class),
            $mode,
            $this->lock = m::mock(LockInterface::class),
            new QueueConfig([
                'memory' => [
                    'connector' => $this->memoryConnector,
                    'consume' => true,
                ],
                'local' => [
                    'connector' => $this->localConnector,
                    'consume' => true,
                ],
                'amqp' => [
                    'connector' => $this->amqpConnector,
                    'consume' => true,
                ],
                'without-connector' => [
                    'consume' => false,
                ],
                'with-wrong-connector' => [
                    'connector' => 'test',
                    'consume' => false,
                ],
                'with-queue-options' => [
                    'connector' => $this->localConnector,
                    'consume' => false,
                    'options' => (new Options())->withDelay(5),
                ],
                'with-jobs-options' => [
                    'connector' => $this->localConnector,
                    'consume' => false,
                    'options' => new KafkaOptions('foo', 100, 14),
                ],
            ]),
            60,
        );
    }

    private function assertLock(string $pipeline, bool $exists = false): void
    {
        $resource = 'rr-jobs-declare-' . $pipeline;
        $id = 'some-lock-id-' . $pipeline;

        $this->lock
            ->shouldReceive('exists')
            ->once()
            ->with($resource)
            ->andReturn($exists);

        if (!$exists) {
            $this->lock
                ->shouldReceive('lock')
                ->once()
                ->with($resource)
                ->andReturn($id);

            $this->lock
                ->shouldReceive('release')
                ->once()
                ->with($resource, $id);
        } else {
            $this->lock
                ->shouldNotReceive('lock')
                ->with($resource);

            $this->lock
                ->shouldNotReceive('release')
                ->with($resource, $id);
        }
    }

    public function testDeclareConsumersPipeline(): void
    {
        $this->makeJob();
        $this->amqpConnector->shouldReceive('getName')->andReturn('amqp');
        $this->memoryConnector->shouldReceive('getName')->andReturn('memory');
        $this->localConnector->shouldReceive('getName')->andReturn('sqs');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(
            new \ArrayIterator([
                'amqp' => '',
            ]),
        );

        $this->assertLock('amqp');
        $this->assertLock('memory');
        $this->assertLock('sqs');

        $this->jobs
            ->shouldReceive('create')
            ->once()
            ->with($this->localConnector)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $queue->shouldReceive('resume')->once();

        $this->jobs
            ->shouldReceive('create')
            ->once()
            ->with($this->memoryConnector)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->jobs
            ->shouldNotReceive('create')
            ->with($this->amqpConnector);

        $queue->shouldReceive('resume')->once();

        $this->registry->declareConsumerPipelines();
    }

    public function testDeclarationShouldBeSkippedIfLocked(): void
    {
        $this->makeJob();
        $this->amqpConnector->shouldReceive('getName')->andReturn('amqp');
        $this->memoryConnector->shouldReceive('getName')->andReturn('memory');
        $this->localConnector->shouldReceive('getName')->andReturn('sqs');

        $this->assertLock('amqp', true);
        $this->assertLock('memory', true);
        $this->assertLock('sqs', true);

        $this->jobs->shouldNotReceive('create');

        $this->registry->declareConsumerPipelines();
    }

    public function testConsumerPipelinesShouldNotBeDeclaredNotInJobsMode(): void
    {
        $this->makeJob(RoadRunnerMode::Tcp);
        $this->jobs->shouldNotReceive('getIterator');
        $this->registry->declareConsumerPipelines();
    }

    public function testGetsExistsPipelineByNameShouldReturnQueue(): void
    {
        $this->makeJob();
        $this->memoryConnector->shouldReceive('getName')->andReturn('local');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['local' => '']));
        $this->jobs
            ->shouldReceive('connect')
            ->once()
            ->with('local', null)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertSame(
            $queue,
            $this->registry->getPipeline('memory'),
        );
    }

    public function testDefaultQueueOptionsShouldBePassedAsJobsOptions(): void
    {
        $this->makeJob();
        $this->localConnector->shouldReceive('getName')->andReturn('with-queue-options');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['with-queue-options' => '']));
        $this->jobs
            ->shouldReceive('connect')
            ->once()
            ->with('with-queue-options', Matchers::equalTo(new JobsOptions(5)))
            ->andReturn(m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('with-queue-options'),
        );
    }

    public function testDefaultJobsOptionsShouldBePassed(): void
    {
        $this->makeJob();
        $this->localConnector->shouldReceive('getName')->andReturn('with-jobs-options');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['with-jobs-options' => '']));
        $this->jobs
            ->shouldReceive('connect')
            ->once()
            ->with('with-jobs-options', Matchers::equalTo(new KafkaOptions('foo', 100, 14)))
            ->andReturn(m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('with-jobs-options'),
        );
    }

    public function testGetsNonExistsPipelineByNameWithoutConsumingShouldCreateItAndReturnQueue(): void
    {
        $this->makeJob();
        $this->localConnector->shouldReceive('getName')->once()->andReturn('local');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['memory']));
        $this->jobs
            ->shouldReceive('create')
            ->once()
            ->with($this->localConnector, null)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('local'),
        );
    }

    public function testGetsNonExistsPipelineShouldReturnQueue(): void
    {
        $this->makeJob();
        $this->jobs
            ->shouldReceive('connect')
            ->once()
            ->with('test')
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertSame($queue, $this->registry->getPipeline('test'));
    }

    public function testGetsPipelineWithoutConnectorShouldThrowAnException(): void
    {
        $this->makeJob();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You must specify connector for given pipeline `without-connector`.');

        $this->registry->getPipeline('without-connector');
    }

    public function testGetsPipelineWithWrongConnectorShouldThrowAnException(): void
    {
        $this->makeJob();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Connector should implement Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface interface.',
        );

        $this->registry->getPipeline('with-wrong-connector');
    }
}
