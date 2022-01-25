<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Mockery as m;
use Spiral\App\GRPC\Echo\EchoInterface;
use Spiral\App\GRPC\Echo\EchoService;
use Spiral\App\GRPC\Echo\Message;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\Tests\TestCase;

final class DispatcherTest extends TestCase
{
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

        $locator = m::mock(LocatorInterface::class);
        $this->container->bind(LocatorInterface::class, $locator);
        $locator->shouldReceive('getServices')->once()->andReturn([
            EchoInterface::class => new EchoService(),
        ]);

        $worker->shouldReceive('waitPayload')->once()->andReturn(new Payload(
            (new Message())->setMsg('PING')->serializeToString(),
            json_encode(['service' => 'service.Echo', 'method' => 'Ping', 'context' => []])
        ));

        $worker->shouldReceive('respond')->once()->withArgs(function (Payload $payload) {
           return $payload->body === (new Message())->setMsg('PONG')->serializeToString();
        });

        $worker->shouldReceive('waitPayload')->once()->with()->andReturnNull();

        $this->app->get(Dispatcher::class)->serve();
    }
}
