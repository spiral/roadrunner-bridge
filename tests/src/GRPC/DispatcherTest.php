<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\App\GRPC\EchoService\Message;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
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
        $this->assertDispatcherCannotBeServed(Dispatcher::class);
    }

    public function testCanServe(): void
    {
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Grpc);
        $this->assertDispatcherCanBeServed(Dispatcher::class);
    }

    public function testServe()
    {
        $worker = $this->mockContainer(WorkerInterface::class, Worker::class);
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Grpc);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
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

        $this->serveDispatcher(Dispatcher::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteGRPCService();
    }
}
