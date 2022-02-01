<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Mockery as m;
use Spiral\App\GRPC\EchoService\EchoInterface;
use Spiral\App\GRPC\EchoService\Message;
use Spiral\Boot\FinalizerInterface;
use Spiral\Files\Files;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher;
use Spiral\Tests\ConsoleTestCase;

final class DispatcherTest extends ConsoleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->generateGRPCService();
    }

    public function testCanServeShouldReturnFalseWithWrongEnvironment(): void
    {
        $this->assertFalse($this->app->get(Dispatcher::class)->canServe());
    }

    public function testCanServe(): void
    {
        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => 'grpc',
            ]);
        });

        $this->assertTrue($this->app->get(Dispatcher::class)->canServe());
    }

    public function testServe()
    {
        $worker = m::mock(Worker::class);
        $this->container->bind(WorkerInterface::class, $worker);

        $this->container->bind(EnvironmentInterface::class, function () {
            return new Environment([
                'RR_MODE' => 'grpc',
            ]);
        });

        $finalizer = m::mock(FinalizerInterface::class);
        $this->container->bind(FinalizerInterface::class, $finalizer);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $worker->shouldReceive('waitPayload')->once()->andReturn(
            new Payload(
                (new Message())->setMsg('PING')->serializeToString(),
                json_encode(['service' => 'service.Echo', 'method' => 'Ping', 'context' => []])
            )
        );

        $worker->shouldReceive('respond')->once()->withArgs(function (Payload $payload) {
            return $payload->body === (new Message())->setMsg('PONG')->serializeToString();
        });

        $worker->shouldReceive('waitPayload')->once()->with()->andReturnNull();

        $this->app->get(Dispatcher::class)->serve();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->deleteGRPCService();
    }
}
