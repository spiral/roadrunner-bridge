<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Hamcrest\Matchers;
use Mockery as m;
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
use Spiral\Tests\TestCase;

final class RPCPipelineRegistryTest extends TestCase
{
    private CreateInfoInterface|m\LegacyMockInterface|m\MockInterface $memoryConnector;
    private CreateInfoInterface|m\LegacyMockInterface|m\MockInterface $localConnector;
    private RPCPipelineRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memoryConnector = m::mock(CreateInfoInterface::class);
        $this->memoryConnector->shouldReceive('toArray')->andReturn([]);
        $this->memoryConnector->shouldReceive('getDriver')->andReturn(Driver::Memory);

        $this->localConnector = m::mock(CreateInfoInterface::class);
        $this->localConnector->shouldReceive('toArray')->andReturn([]);
        $this->localConnector->shouldReceive('getDriver')->andReturn(Driver::SQS);

        $this->registry = new RPCPipelineRegistry(
            $this->jobs = m::mock(JobsInterface::class),
            new QueueConfig([
                'memory' => [
                    'connector' => $this->memoryConnector,
                    'consume' => true,
                ],
                'local' => [
                    'connector' => $this->localConnector,
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
            60
        );
    }

    public function testDeclareConsumersPipeline(): void
    {
        $this->jobs->shouldReceive('create')
            ->once()
            ->with($this->memoryConnector)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $queue->shouldReceive('resume')->once();

        $this->jobs->shouldReceive('create')
            ->once()
            ->with($this->localConnector)
            ->andReturn($queue = m::mock(QueueInterface::class));
        $queue->shouldReceive('resume')->once();

        $this->registry->declareConsumerPipelines();
    }

    public function testGetsExistsPipelineByNameShouldReturnQueue(): void
    {
        $this->memoryConnector->shouldReceive('getName')->andReturn('local');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['local' => '']));
        $this->jobs->shouldReceive('connect')
            ->once()
            ->with('local', null)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('memory'),
        );
    }

    public function testDefaultQueueOptionsShouldBePassedAsJobsOptions(): void
    {
        $this->localConnector->shouldReceive('getName')->andReturn('with-queue-options');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['with-queue-options' => '']));
        $this->jobs->shouldReceive('connect')
            ->once()
            ->with('with-queue-options', Matchers::equalTo(new JobsOptions(5)))
            ->andReturn(m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('with-queue-options', 'some'),
        );
    }

    public function testDefaultJobsOptionsShouldBePassed(): void
    {
        $this->localConnector->shouldReceive('getName')->andReturn('with-jobs-options');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['with-jobs-options' => '']));
        $this->jobs->shouldReceive('connect')
            ->once()
            ->with('with-jobs-options', Matchers::equalTo(new KafkaOptions('foo', 100, 14)))
            ->andReturn(m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('with-jobs-options', 'some'),
        );
    }

    public function testGetsNonExistsPipelineByNameWithoutConsumingShouldCreateItAndReturnQueue(): void
    {
        $this->localConnector->shouldReceive('getName')->once()->andReturn('local');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['memory']));
        $this->jobs->shouldReceive('create')
            ->once()
            ->with($this->localConnector, null)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('local', 'some'),
        );
    }

    public function testGetsNonExistsPipelineShouldReturnQueue(): void
    {
        $this->jobs->shouldReceive('connect')
            ->once()
            ->with('test')
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertSame($queue, $this->registry->getPipeline('test'));
    }

    public function testGetsPipelineWithoutConnectorShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('You must specify connector for given pipeline `without-connector`.');

        $this->registry->getPipeline('without-connector', 'some');
    }

    public function testGetsPipelineWithWrongConnectorShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage(
            'Connector should implement Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface interface.',
        );

        $this->registry->getPipeline('with-wrong-connector', 'some');
    }
}
