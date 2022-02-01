<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Mockery as m;
use Spiral\Core\ConfigsInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\QueueInterface;
use Spiral\Queue\SerializerInterface;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Serializer\SerializerInterface as RRSerializerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Queue\Dispatcher;
use Spiral\RoadRunnerBridge\Queue\JobsAdapterSerializer;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\RPCPipelineRegistry;
use Spiral\Snapshots\SnapshotterInterface;
use Spiral\Tests\TestCase;

final class QueueBootloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->bind(SnapshotterInterface::class, function () {
            return m::mock(SnapshotterInterface::class);
        });
    }

    public function testGetsHandlerRegistryInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            HandlerRegistryInterface::class,
            \Spiral\Queue\QueueRegistry::class
        );

        $this->assertContainerBoundAsSingleton(
            \Spiral\Queue\QueueRegistry::class,
            \Spiral\Queue\QueueRegistry::class
        );
    }

    public function testGetsPipelineRegistryInterface(): void
    {
        $this->assertInstanceOf(
            RPCPipelineRegistry::class,
            $registry1 = $this->container->make(PipelineRegistryInterface::class, [
                'pipelines' => ['foo' => 'bar'],
                'aliases' => ['bas' => 'bar'],
            ])
        );
        $this->assertInstanceOf(
            RPCPipelineRegistry::class,
            $registry2 = $this->container->make(PipelineRegistryInterface::class, [
                'pipelines' => ['foo' => 'bar'],
                'aliases' => ['bas' => 'bar'],
            ])
        );

        $this->assertNotSame($registry1, $registry2);
    }

    public function testGetsFailedJobHandlerInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            \Spiral\Queue\Failed\FailedJobHandlerInterface::class,
            \Spiral\Queue\Failed\LogFailedJobHandler::class
        );
    }

    public function testGetsQueueManager(): void
    {
        $this->assertContainerBoundAsSingleton(
            \Spiral\Queue\QueueManager::class,
            \Spiral\Queue\QueueManager::class
        );
    }

    public function testDispatcherShouldBeRegistered(): void
    {
        $dispatchers = $this->accessProtected($this->app, 'dispatchers');

        $this->assertCount(
            1,
            array_filter($dispatchers, function ($dispatcher) {
                return $dispatcher instanceof Dispatcher;
            })
        );
    }

    public function testGetsSerializerInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            SerializerInterface::class,
            \Spiral\Queue\DefaultSerializer::class
        );
    }

    public function testGetsRRSerializerInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            RRSerializerInterface::class,
            JobsAdapterSerializer::class
        );
    }

    public function testGetsConsumerInterface(): void
    {
        $this->container->bind(WorkerInterface::class, function () {
            return m::mock(WorkerInterface::class);
        });

        $this->assertContainerBoundAsSingleton(
            ConsumerInterface::class,
            Consumer::class
        );
    }

    public function testGetsQueueInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            QueueInterface::class,
            \Spiral\Queue\Driver\SyncDriver::class
        );
    }

    public function testConfigShouldBeDefined(): void
    {
        $configurator = $this->container->get(ConfigsInterface::class);
        $config = $configurator->getConfig('queue');

        $this->assertIsArray($config);
    }
}
