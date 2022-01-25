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
            'connections' => [
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

    public function testGetsDefaultConnection()
    {
        $this->assertInstanceOf(
            ShortCircuit::class,
            $this->manager->getConnection()
        );
    }

    public function testGetsConnectionByNameWithDriverAlias()
    {
        $this->assertInstanceOf(
            Queue::class,
            $queue = $this->manager->getConnection('memory')
        );

        $this->assertSame('foo', $queue->getName());
    }

    public function testGetsConnectionByNameWithoutDriverAlias()
    {
        $this->assertInstanceOf(
            Queue::class,
            $queue = $this->manager->getConnection('localMemory')
        );

        $this->assertSame('bar', $queue->getName());
    }

    public function testGetsConnectionByAlias()
    {
        $this->assertInstanceOf(
            Queue::class,
            $queue = $this->manager->getConnection('user-data')
        );

        $this->assertSame('foo', $queue->getName());
        $this->assertSame($queue, $this->manager->getConnection('memory'));
    }
}
