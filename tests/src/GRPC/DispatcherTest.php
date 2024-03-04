<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Service\PingService;
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

    public function testServe(): void
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
            $this->assertSame($payload->body, (new Message())->setMsg('PONG')->serializeToString());
            return true;
        });

        $worker->shouldReceive('waitPayload')->once()->with()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    public function testGrpcScope(): void
    {
        $this->assertEquals([], PingService::$scopes);

        $worker = $this->mockContainer(WorkerInterface::class, Worker::class);
        $this->getContainer()->bind(RoadRunnerMode::class, RoadRunnerMode::Grpc);

        $finalizer = $this->mockContainer(FinalizerInterface::class);
        $finalizer->shouldReceive('finalize')->once()->with(false);

        $worker->shouldReceive('waitPayload')->once()->andReturn(
            new Payload(
                (new \Service\Message())->setMsg('PING')->serializeToString(),
                json_encode(['service' => 'service.Ping', 'method' => 'Ping', 'context' => []])
            )
        );

        $worker->shouldReceive('respond')->once()->withArgs(function (Payload $payload) {
            $this->assertSame($payload->body, (new \Service\Message())->setMsg('PONG')->serializeToString());
            return true;
        });

        $worker->shouldReceive('waitPayload')->once()->with()->andReturnNull();

        $this->getApp()->serve();

        $this->assertEquals(['grpc.request', 'grpc', 'root'], PingService::$scopes);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteGRPCService();
    }
}
