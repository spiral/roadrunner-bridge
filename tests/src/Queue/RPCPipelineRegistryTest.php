<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
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

        $this->registry = (new RPCPipelineRegistry(
            $this->jobs = m::mock(JobsInterface::class),
            [
                'memory' => [
                    'connector' => $this->memoryConnector = m::mock(CreateInfoInterface::class),
                    'cunsume' => true,
                ],
                'local' => [
                    'connector' => $this->localConnector = m::mock(CreateInfoInterface::class),
                    'consume' => false,
                ],
                'without-connector' => [
                    'cunsume' => true,
                ],
                'with-wrong-connector' => [
                    'connector' => 'test',
                    'cunsume' => true,
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
            ->with('local')
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('memory')
        );
    }

    public function testGetsNonExistsPipelineByNameShouldCreateItAndReturnQueue(): void
    {
        $this->memoryConnector->shouldReceive('getName')->once()->andReturn('local');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['memory']));
        $this->jobs->shouldReceive('create')
            ->once()
            ->with($this->memoryConnector)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $queue->shouldReceive('resume')->once();

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('memory')
        );
    }

    public function testGetsNonExistsPipelineByNameWithoutConsumingShouldCreateItAndReturnQueue(): void
    {
        $this->localConnector->shouldReceive('getName')->once()->andReturn('local');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['memory']));
        $this->jobs->shouldReceive('create')
            ->once()
            ->with($this->localConnector)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('local')
        );
    }

    public function testGetsExistsPipelineByAliasShouldReturnQueue(): void
    {
        $this->memoryConnector->shouldReceive('getName')->once()->andReturn('local');

        $this->jobs->shouldReceive('getIterator')->once()->andReturn(new \ArrayIterator(['memory']));
        $this->jobs->shouldReceive('create')
            ->once()
            ->with($this->memoryConnector)
            ->andReturn($queue = m::mock(QueueInterface::class));

        $queue->shouldReceive('resume')->once();

        $this->assertInstanceOf(
            QueueInterface::class,
            $this->registry->getPipeline('user-data')
        );
    }

    public function testGetsNonExistsPipelineShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Queue pipeline with given name `test` is not found.');

        $this->registry->getPipeline('test');
    }

    public function testGetsNonExistsAliasPipelineShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Queue pipeline with given name `test` is not found.');

        $this->registry->getPipeline('bad-alias');
    }

    public function testGetsPipelineWithoutConnectorShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('You must specify connector for given pipeline `without-connector`.');

        $this->registry->getPipeline('without-connector');
    }

    public function testGetsPipelineWithWrongConnectorShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Connector should implement Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface interface.');

        $this->registry->getPipeline('with-wrong-connector');
    }
}
