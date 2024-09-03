<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Core\Container;
use Spiral\Core\FactoryInterface;
use Spiral\Queue\Config\QueueConfig;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueInterface;
use Spiral\Queue\QueueManager;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\Tests\TestCase;

class QueueManagerTest extends TestCase
{
    public function testGetsRoadRunnerQueue(): void
    {
        $manager = $this->getContainer()->get(QueueConnectionProviderInterface::class);

        $queue = $manager->getConnection('roadrunner');

        $core = $this->accessProtected($queue, 'core');
        $core = $this->accessProtected($core, 'handler');
        $connection = $this->accessProtected($core, 'connection');

        $this->assertInstanceOf(
            Queue::class,
            $connection,
        );
    }

    public function testPushIntoDefaultRoadRunnerPipeline()
    {
        $factory = m::mock(FactoryInterface::class);

        $factory->shouldReceive('make')->once()
            ->with('roadrunner', [
                'driver' => 'roadrunner',
                'pipelines' => [],
            ])
            ->andReturn($driver = m::mock(QueueInterface::class));

        $manager = new QueueManager(
            new QueueConfig([
                'connections' => [
                    'roadrunner' => [
                        'driver' => 'roadrunner',
                        'pipelines' => [],
                    ],
                ],
            ]),
            new Container(),
            $factory
        );

        $queue = $manager->getConnection('roadrunner');

        $driver->shouldReceive('push')
            ->once()
            ->withSomeOfArgs('foo', ['boo' => 'bar'])->andReturn('task-id');

        $this->assertSame('task-id', $queue->push('foo', ['boo' => 'bar']));
    }
}
