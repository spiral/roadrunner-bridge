<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Hamcrest\Matchers;
use Mockery as m;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\Queue\Options;
use Spiral\RoadRunner\Jobs\Options as JobsOptions;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Queue\JobsAdapterSerializer;
use Spiral\RoadRunnerBridge\Queue\RPCPipelineRegistry;
use Spiral\Tests\TestCase;

final class RPCPipelineRegistryTest extends TestCase
{
    /** @var CreateInfoInterface|m\LegacyMockInterface|m\MockInterface */
    private $memoryConnector;
    /** @var CreateInfoInterface|m\LegacyMockInterface|m\MockInterface */
    private $localConnector;
    private RPCPipelineRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memoryConnector = m::mock(CreateInfoInterface::class);
        $this->memoryConnector->shouldReceive('toArray')->andReturn([]);
        $this->memoryConnector->shouldReceive('getDriver')->andReturn('foo');

        $this->localConnector = m::mock(CreateInfoInterface::class);
        $this->localConnector->shouldReceive('toArray')->andReturn([]);
        $this->localConnector->shouldReceive('getDriver')->andReturn('foo');

        $this->registry = (new RPCPipelineRegistry(
            $this->jobs = m::mock(JobsInterface::class),
            $this->getContainer()->get(JobsAdapterSerializer::class),
            [
                'memory' => [
                    'connector' => $this->memoryConnector,
                    'cunsume' => true,
                ],
                'local' => [
                    'connector' => $this->localConnector,
                    'consume' => false,
                ],
                'without-connector' => [
                    'cunsume' => true,
                ],
                'with-wrong-connector' => [
                    'connector' => 'test',
                    'cunsume' => true,
                ],
                'with-queue-options' => [
                    'connector' => $this->localConnector,
                    'cunsume' => true,
                    'options' => (new Options())->withDelay(5)
                ],
                'with-jobs-options' => [
                    'connector' => $this->localConnector,
                    'cunsume' => true,
                    'options' => new KafkaOptions('foo', 100, 14)
                ],
            ],
            [
                'user-data' => 'memory',
                'bad-alias' => 'test',
            ],
            60
        ));
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
            $this->registry->getPipeline('memory', 'some')
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
            $this->registry->getPipeline('with-queue-options', 'some')
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
            $this->registry->getPipeline('with-jobs-options', 'some')
        );
    }

    public function testGetsNonExistsPipelineByNameShouldCreateItAndReturnQueue(): void
    {
        $this->memoryConnector->shouldReceive('getName')->once()->andReturn('local');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['memory']));
        $this->jobs->shouldReceive('create')
            ->once()
            ->with($this->memoryConnector, null)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $queue->shouldReceive('resume')->once();

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('memory', 'some')
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
            $this->registry->getPipeline('local', 'some')
        );
    }

    public function testGetsExistsPipelineByAliasShouldReturnQueue(): void
    {
        $this->memoryConnector->shouldReceive('getName')->once()->andReturn('local');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['memory']));
        $this->jobs->shouldReceive('create')
            ->once()
            ->with($this->memoryConnector, null)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $queue->shouldReceive('resume')->once();

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('user-data', 'some')
        );
    }

    public function testGetsNonExistsPipelineShouldReturnQueue(): void
    {
        $this->jobs->shouldReceive('connect')
            ->once()
            ->with('test')
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertSame($queue, $this->registry->getPipeline('test', 'some'));
    }

    public function testGetsNonExistsAliasPipelineShouldReturnQueue(): void
    {
        $this->jobs->shouldReceive('connect')
            ->once()
            ->with('test')
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertSame($queue, $this->registry->getPipeline('bad-alias', 'some'));
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
        $this->expectErrorMessage('Connector should implement Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface interface.');

        $this->registry->getPipeline('with-wrong-connector', 'some');
    }
}
