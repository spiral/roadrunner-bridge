<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Mockery as m;
use Spiral\Core\ConfigsInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
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
use Spiral\Tests\TestCase;

final class QueueBootloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->bind(ExceptionReporterInterface::class, function () {
            return m::mock(ExceptionReporterInterface::class);
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
            $registry1 = $this->getContainer()->make(PipelineRegistryInterface::class, [
                'pipelines' => ['foo' => 'bar'],
                'aliases' => ['bas' => 'bar'],
            ])
        );
        $this->assertInstanceOf(
            RPCPipelineRegistry::class,
            $registry2 = $this->getContainer()->make(PipelineRegistryInterface::class, [
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
        $this->assertDispatcherRegistered(Dispatcher::class);
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
        $this->getContainer()->bind(WorkerInterface::class, function () {
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
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig('queue');

        $this->assertIsArray($config);
    }
}
