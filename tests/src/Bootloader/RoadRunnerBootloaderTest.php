<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\Tests\TestCase;

final class RoadRunnerBootloaderTest extends TestCase
{
    public function testGetsEnvironmentInterface()
    {
        $this->assertContainerBoundAsSingleton(
            EnvironmentInterface::class,
            Environment::class,
        );

        $this->assertContainerBoundAsSingleton(
            Environment::class,
            Environment::class,
        );
    }

    public function testGetsRPCInterface()
    {
        $this->assertContainerBoundAsSingleton(
            RPCInterface::class,
            RPC::class,
        );

        $this->assertContainerBoundAsSingleton(
            RPC::class,
            RPC::class,
        );
    }

    public function testGetsWorkerInterface()
    {
        $this->assertContainerBoundAsSingleton(
            WorkerInterface::class,
            Worker::class,
        );

        $this->assertContainerBoundAsSingleton(
            Worker::class,
            Worker::class,
        );

        // TODO fix problem with rr worker
        ob_end_flush();
    }

    public function testGetsPSR7WorkerInterface()
    {
        $this->assertContainerBoundAsSingleton(
            PSR7WorkerInterface::class,
            PSR7Worker::class,
        );

        $this->assertContainerBoundAsSingleton(
            PSR7Worker::class,
            PSR7Worker::class,
        );

        // TODO fix problem with rr worker
        ob_end_flush();
    }

    public function testDefaultPSR7WorkerChunkSize(): void
    {
        $worker = $this->container->get(PSR7Worker::class);
        $this->assertSame(0, $worker->chunkSize);

        // TODO fix problem with rr worker
        ob_end_flush();
    }

    public function testPSR7WorkerChunkSize(): void
    {
        $this->updateConfig('http.chunkSize', 100);

        $worker = $this->container->get(PSR7Worker::class);
        $this->assertSame(100, $worker->chunkSize);

        // TODO fix problem with rr worker
        ob_end_flush();
    }
}
