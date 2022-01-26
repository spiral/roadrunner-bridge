<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\RoadRunnerBridge\Queue\QueueManager;
use Spiral\RoadRunnerBridge\Queue\ShortCircuit;
use Spiral\Tests\TestCase;

final class QueueManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $config = new QueueConfig([
            'default' => 'sync',
            'aliases' => [
                'user-data' => 'memory',
            ],
            'pipelines' => [
                'sync' => [
                    'driver' => 'sync',
                ],
                'sync_without_alias' => [
                    'driver' => ShortCircuit::class,
                ],
                'memory' => [
                    'driver' => 'roadrunner',
                    'connector' => new MemoryCreateInfo('foo'),
                ],
                'localMemory' => [
                    'driver' => 'roadrunner',
                    'connector' => new MemoryCreateInfo('bar'),
                    'consume' => false,
                ],
                'amqp' => [
                    'driver' => Queue::class,
                    'connector' => new AMQPCreateInfo('amqp'),
                ],
            ],
            'driverAliases' => [
                'sync' => ShortCircuit::class,
                'roadrunner' => Queue::class,
            ],
        ]);

        $this->beforeBootload(function ($container) use ($config) {
            $container->bind(QueueConfig::class, $config);
        });

        parent::setUp();

        $this->manager = new QueueManager($config, $this->container);
    }

    public function testGetsDefaultPipeline()
    {
        $this->assertInstanceOf(
            ShortCircuit::class,
            $this->manager->getPipeline()
        );
    }

    public function testGetsPipelineByNameWithDriverAlias()
    {
        $this->assertInstanceOf(
            Queue::class,
            $queue = $this->manager->getPipeline('memory')
        );

        $this->assertSame('foo', $queue->getName());
    }

    public function testGetsPipelineByNameWithoutDriverAlias()
    {
        $this->assertInstanceOf(
            Queue::class,
            $queue = $this->manager->getPipeline('localMemory')
        );

        $this->assertSame('bar', $queue->getName());
    }

    public function testGetsPipelineByAlias()
    {
        $this->assertInstanceOf(
            Queue::class,
            $queue = $this->manager->getPipeline('user-data')
        );

        $this->assertSame('foo', $queue->getName());
        $this->assertSame($queue, $this->manager->getPipeline('memory'));
    }
}
