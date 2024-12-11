<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\App\GRPC\Ping\PingResponse;
use Spiral\Boot\FinalizerInterface;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\GRPC\Dispatcher;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Tests\ConsoleTestCase;

final class DispatcherTest extends ConsoleTestCase
{
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
                (new PingResponse())->serializeToString(),
                \json_encode(['service' => 'service.Echo', 'method' => 'Ping', 'context' => []]),
            ),
        );

        $worker->shouldReceive('respond')->once()->withArgs(function (Payload $payload) {
            $this->assertSame($payload->body, (new PingResponse())->serializeToString());
            return true;
        });

        $worker->shouldReceive('waitPayload')->once()->with()->andReturnNull();

        $this->serveDispatcher(Dispatcher::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->generateGRPCService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteGRPCService();
    }
}
