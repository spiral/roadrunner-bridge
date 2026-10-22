<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Mockery as m;
use Spiral\Core\ConfigsInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Internal\Dispatcher;
use Spiral\RoadRunnerBridge\Queue\Internal\PayloadDeserializer;
use Spiral\RoadRunnerBridge\Queue\Internal\RPCPipelineRegistry;
use Spiral\RoadRunnerBridge\Queue\PayloadDeserializerInterface;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\Serializer\SerializerInterface;
use Spiral\Serializer\SerializerManager;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

final class QueueBootloaderTest extends TestCase
{
    #[Test]
    public function getsHandlerRegistryInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            HandlerRegistryInterface::class,
            \Spiral\Queue\QueueRegistry::class,
        );

        $this->assertContainerBoundAsSingleton(
            \Spiral\Queue\QueueRegistry::class,
            \Spiral\Queue\QueueRegistry::class,
        );
    }

    #[Test]
    public function getsPipelineRegistryInterface(): void
    {
        Assert::instanceOf($registry1 = $this->getContainer()->make(PipelineRegistryInterface::class, [
            'pipelines' => ['foo' => 'bar'],
            'aliases' => ['bas' => 'bar'],
        ]), RPCPipelineRegistry::class);
        Assert::instanceOf($registry2 = $this->getContainer()->make(PipelineRegistryInterface::class, [
            'pipelines' => ['foo' => 'bar'],
            'aliases' => ['bas' => 'bar'],
        ]), RPCPipelineRegistry::class);

        Assert::notSame($registry2, $registry1);
    }

    #[Test]
    public function getsFailedJobHandlerInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            \Spiral\Queue\Failed\FailedJobHandlerInterface::class,
            \Spiral\Queue\Failed\LogFailedJobHandler::class,
        );
    }

    #[Test]
    public function getsQueueManager(): void
    {
        $this->assertContainerBoundAsSingleton(
            \Spiral\Queue\QueueManager::class,
            \Spiral\Queue\QueueManager::class,
        );
    }

    #[Test]
    public function dispatcherShouldBeRegistered(): void
    {
        $this->assertDispatcherRegistered(Dispatcher::class);
    }

    #[Test]
    public function getsSerializerInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            SerializerInterface::class,
            SerializerManager::class,
        );
    }

    #[Test]
    public function getsPayloadDeserializerInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            PayloadDeserializerInterface::class,
            PayloadDeserializer::class,
        );
    }

    #[Test]
    public function configShouldBeDefined(): void
    {
        $configurator = $this->getContainer()->get(ConfigsInterface::class);
        $config = $configurator->getConfig('queue');

        Assert::array($config);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->getContainer()->bind(ExceptionReporterInterface::class, static function () {
            return m::mock(ExceptionReporterInterface::class);
        });
    }
}
